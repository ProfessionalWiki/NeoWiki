<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\ReplicaCacheOptions;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaJsonLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;

/**
 * The Schema of a Subject is needed by both the validation and the graph projection of every
 * Subject on a saved page. These pin that a page's Schemas are resolved once per Schema, not
 * once per Subject per path.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 * @group Database
 */
class SchemaLookupSharingTest extends NeoWikiIntegrationTestCase {

	private const SCHEMA_NAME = 'SharedLookupSchema';

	public function setUp(): void {
		$this->setUpNeo4j();
		$this->createSchema( self::SCHEMA_NAME );
	}

	public function testProjectingAPageResolvesItsSchemaOnce(): void {
		$inner = $this->newCountingLookup();

		$this->newCountingProjectionStore( $inner )->savePage(
			TestPage::build(
				mainSubject: $this->newSubject( 'sShrdLookup1111' ),
				otherSubjects: new SubjectMap(
					$this->newSubject( 'sShrdLookup1112' ),
					$this->newSubject( 'sShrdLookup1113' ),
					$this->newSubject( 'sShrdLookup1114' ),
				)
			)
		);

		$this->assertSame( 1, $inner->calls );
	}

	public function testValidatingSubjectsResolvesTheirSchemaOnce(): void {
		$inner = $this->newCountingLookup();
		$validator = new ProposedSubjectValidator(
			schemaResolver: TestSources::newSchemaResolver( $this->newCachingLookup( $inner ) ),
			subjectValidator: NeoWikiExtension::getInstance()->newSubjectValidator( $this->getTestUser()->getAuthority() ),
		);

		$validator->validate( $this->newSubject( 'sShrdLookup1115' ) );
		$validator->validate( $this->newSubject( 'sShrdLookup1116' ) );
		$validator->validate( $this->newSubject( 'sShrdLookup1117' ) );
		$validator->validate( $this->newSubject( 'sShrdLookup1118' ) );

		$this->assertSame( 1, $inner->calls );
	}

	public function testServesOneSchemaLookupThroughoutARequest(): void {
		$extension = NeoWikiExtension::getInstance();

		$this->assertSame( $extension->getSchemaLookup(), $extension->getSchemaLookup() );
	}

	/**
	 * The parser holds what the normalizer remembers, so a fresh one per read would parse every Schema
	 * name again on every page.
	 */
	public function testServesOneSchemaReferenceParserThroughoutARequest(): void {
		$extension = NeoWikiExtension::getInstance();

		$this->assertSame( $extension->getSchemaReferenceParser(), $extension->getSchemaReferenceParser() );
	}

	private function newCountingProjectionStore( SchemaJsonLookup $inner ): GraphDatabasePlugin {
		return NeoWikiExtension::getInstance()->newNeo4jProjectionStore( $this->newCachingLookup( $inner ) );
	}

	private function newCachingLookup( SchemaJsonLookup $inner ): CachingSchemaLookup {
		$services = MediaWikiServices::getInstance();

		return new CachingSchemaLookup(
			schemaJsonLookup: $inner,
			schemaDeserializer: new SchemaPersistenceDeserializer(
				NeoWikiExtension::getInstance()->getPropertyTypeLookup()
			),
			// Stores nothing, so every read past the process-local tier reaches the counting lookup.
			cache: new WANObjectCache( [ 'cache' => new EmptyBagOStuff() ] ),
			titleFactory: $services->getTitleFactory(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: true ),
			cacheOptions: new ReplicaCacheOptions( $services->getConnectionProvider(), MW_VERSION ),
		);
	}

	private function newSubject( string $id ): Subject {
		return TestSubject::build( id: $id, schemaName: new SchemaName( self::SCHEMA_NAME ) );
	}

	/**
	 * @return SchemaJsonLookup&object{calls: int}
	 */
	private function newCountingLookup(): SchemaJsonLookup {
		return new class() implements SchemaJsonLookup {
			public int $calls = 0;

			public function getSchemaJson( Title $schemaPage ): string {
				$this->calls++;
				return '{"description":"","propertyDefinitions":{}}';
			}
		};
	}

}
