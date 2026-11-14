<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Content;

use MediaWiki\Parser\ParserOptions;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\ParseTimePermissionFixtures;

/**
 * Needs the integration base and the database for the pages it saves and the parse that renders them.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContentHandler
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\ParserPageDependencyRecorder
 * @group Database
 */
class SubjectContentHandlerParserOutputTest extends NeoWikiIntegrationTestCase {

	use ParseTimePermissionFixtures;

	private const string PAGE = 'SubjectContentHandlerParserOutputTestPage';

	public function testPageDependsOnTheSchemaOfEachOfItsSubjects(): void {
		$this->createSchema( 'Person' );
		$this->createSchema( 'Company' );

		$this->createPageWithSubjects(
			self::PAGE,
			TestSubject::build( id: 's11111111111111', schemaName: new SchemaName( 'Person' ) ),
			new SubjectMap( TestSubject::build( id: 's11111111111112', schemaName: new SchemaName( 'Company' ) ) )
		);

		$this->assertEqualsCanonicalizing( [ 'Schema:Person', 'Schema:Company' ], $this->pagesThePageDependsOn() );
	}

	public function testPageAnonymousUsersCannotReadDoesNotRevealTheSchemasOfItsSubjects(): void {
		$this->denyAnonymousReadOf( self::PAGE );

		$this->createPageWithSubjects( self::PAGE, TestSubject::build( schemaName: new SchemaName( 'Person' ) ) );

		$this->assertSame( [], $this->pagesThePageDependsOn() );
	}

	/**
	 * @dataProvider subjectWithoutASchemaProvider
	 */
	public function testSubjectWithoutASchemaLeavesTheOtherSubjectsDependingOnTheirs( string $subjectJson ): void {
		$this->writeSubjectSlot(
			self::PAGE,
			'{"subjects":{"s11111111111111":' . $subjectJson . ',"s11111111111112":{"schema":"Person","statements":{}}}}'
		);

		$this->assertSame( [ 'Schema:Person' ], $this->pagesThePageDependsOn() );
	}

	public static function subjectWithoutASchemaProvider(): iterable {
		yield 'not a Subject' => [ '"not a Subject"' ];
		yield 'an empty Schema name' => [ '{"schema":"","statements":{}}' ];
	}

	public function testSubjectWhoseValuesDoNotDeserializeDependsOnItsSchema(): void {
		$this->writeSubjectSlot(
			self::PAGE,
			'{"subjects":{"s11111111111111":{"schema":"Company","statements":{"Founded":{"propertyType":"number","value":"1990"}}}}}'
		);

		$this->assertSame( [ 'Schema:Company' ], $this->pagesThePageDependsOn() );
	}

	/**
	 * @return string[]
	 */
	private function pagesThePageDependsOn(): array {
		$page = $this->getServiceContainer()->getPageStore()->getPageByName( NS_MAIN, self::PAGE );
		$this->assertNotNull( $page );

		$output = $this->getServiceContainer()->getParserOutputAccess()
			->getParserOutput( $page, ParserOptions::newFromAnon() )->getValue();
		$this->assertNotNull( $output );

		return $this->templatesOf( $output );
	}

}
