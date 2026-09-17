<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Search;

use ISearchResultSet;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MainConfigNames;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use Wikimedia\Rdbms\DatabaseSqlite;

/**
 * What a reader of Special:Search finds, against a real full-text index. Needs a database that has one,
 * which is MySQL, and SQLite when PHP's SQLite was built with FTS3. {@see SubjectSearchIndexingTest}
 * covers what is handed to the engine on any database.
 *
 * @group Database
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Search\SearchMySQLWithSubjects
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Search\SearchSqliteWithSubjects
 */
class DatabaseSearchEndToEndTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();

		$this->skipWithoutAFullTextIndex();

		// The test framework configures SearchEngineDummy. Without a configured engine, MediaWiki picks
		// one from the database type, which NeoWiki's SearchMappings entries answer with its own.
		$this->overrideConfigValue( MainConfigNames::SearchType, null );

		// The test framework turns the Pig Latin variant of English on, which no wiki does by default.
		// Before MediaWiki 1.44 the SQLite engine required a term's variants all at once, so with it on
		// every search finds nothing.
		$this->overrideConfigValue( MainConfigNames::UsePigLatinVariant, false );
	}

	private function skipWithoutAFullTextIndex(): void {
		$databaseType = $this->getDb()->getType();

		if ( $databaseType === 'mysql' ) {
			return;
		}

		if ( $databaseType === 'sqlite' && DatabaseSqlite::getFulltextSearchModule() === 'FTS3' ) {
			return;
		}

		$this->markTestSkipped( 'Full-text search in tests needs MySQL, or SQLite built with FTS3' );
	}

	public function testStatementValueIsFound(): void {
		$this->createPageWithSubjects( 'Museum page', $this->museumIn( 'Amsterdam' ) );
		DeferredUpdates::doUpdates();

		$this->assertSame( [ 'Museum page' ], $this->pagesMatching( 'Amsterdam' ) );
	}

	public function testValueRemovedFromASubjectIsNoLongerFound(): void {
		$this->createPageWithSubjects( 'Museum page', $this->museumIn( 'Amsterdam' ) );
		DeferredUpdates::doUpdates();

		$this->changeSubjectsOfPage( 'Museum page', TestSubject::build( label: 'Rijksmuseum' ) );
		DeferredUpdates::doUpdates();

		$this->assertSame( [], $this->pagesMatching( 'Amsterdam' ) );
	}

	private function museumIn( string $city ): Subject {
		return TestSubject::build(
			label: 'Rijksmuseum',
			statements: new StatementList( [ TestStatement::build( property: 'City', value: $city ) ] )
		);
	}

	/**
	 * @return string[] The names of the pages the search finds, sorted
	 */
	private function pagesMatching( string $term ): array {
		$results = $this->getServiceContainer()->getSearchEngineFactory()->create()->searchText( $term );
		$this->assertInstanceOf( ISearchResultSet::class, $results );

		$names = [];

		foreach ( $results as $result ) {
			$names[] = $result->getTitle()->getPrefixedText();
		}

		sort( $names );

		return $names;
	}

}
