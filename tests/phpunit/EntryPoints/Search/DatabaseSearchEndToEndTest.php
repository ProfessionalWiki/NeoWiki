<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Search;

use ISearchResultSet;
use MediaWiki\Deferred\DeferredUpdates;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;

/**
 * What a reader of Special:Search finds, against a real full-text index. Needs a database that has one,
 * which is MySQL, and SQLite when PHP's SQLite was built with FTS3. {@see SubjectSearchIndexingTest}
 * covers what is handed to the engine on any database.
 *
 * @group Database
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Search\SearchMySQLWithSubjects
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Search\SearchSqliteWithSubjects
 */
class DatabaseSearchEndToEndTest extends FullTextSearchTestCase {

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
