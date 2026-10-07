<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\Search;

use MediaWiki\MainConfigNames;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use Wikimedia\Rdbms\DatabaseSqlite;

/**
 * For the tests that need a real full-text index, which is MySQL, and SQLite when PHP's SQLite was
 * built with FTS3.
 */
abstract class FullTextSearchTestCase extends NeoWikiIntegrationTestCase {

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

}
