<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\MediaWikiServices;
use ProfessionalWiki\NeoWiki\Application\SchemaLookup;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\CachingSchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageReadAuthorizer;
use Wikimedia\ObjectCache\EmptyBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;

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
				childSubjects: new SubjectMap(
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
			schemaLookup: $this->newCachingLookup( $inner ),
			subjectValidator: NeoWikiExtension::getInstance()->getSubjectValidator(),
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

	private function newCountingProjectionStore( SchemaLookup $inner ): GraphDatabasePlugin {
		return NeoWikiExtension::getInstance()->newNeo4jProjectionStore( $this->newCachingLookup( $inner ) );
	}

	private function newCachingLookup( SchemaLookup $inner ): CachingSchemaLookup {
		$services = MediaWikiServices::getInstance();

		return new CachingSchemaLookup(
			schemaLookup: $inner,
			// Stores nothing, so every read past the process-local tier reaches the counting lookup.
			cache: new WANObjectCache( [ 'cache' => new EmptyBagOStuff() ] ),
			titleFactory: $services->getTitleFactory(),
			readAuthorizer: new StubPageReadAuthorizer( allowed: true ),
			connectionProvider: $services->getConnectionProvider(),
		);
	}

	private function newSubject( string $id ): Subject {
		return TestSubject::build( id: $id, schemaName: new SchemaName( self::SCHEMA_NAME ) );
	}

	/**
	 * @return SchemaLookup&object{calls: int}
	 */
	private function newCountingLookup(): SchemaLookup {
		return new class() implements SchemaLookup {
			public int $calls = 0;

			public function getSchema( SchemaName $schemaName ): ?Schema {
				$this->calls++;
				return TestSchema::build( name: $schemaName );
			}
		};
	}

}
