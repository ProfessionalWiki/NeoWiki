<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\NeoWikiTables;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Runs against a scratch table rather than NeoWiki's own, which the tests after this one need.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\NeoWikiTables
 * @group Database
 */
class NeoWikiTablesTest extends NeoWikiIntegrationTestCase {

	private const string SCRATCH_TABLE = 'neowiki_tables_test_scratch';
	private const string MISSING_TABLE = 'neowiki_tables_test_missing';

	protected function setUp(): void {
		parent::setUp();
		$this->createScratchTable( self::SCRATCH_TABLE );
	}

	protected function tearDown(): void {
		$this->getDb()->dropTable( self::SCRATCH_TABLE, __METHOD__ );
		parent::tearDown();
	}

	public function testListsTheTablesThatExist(): void {
		$this->assertSame( [ self::SCRATCH_TABLE ], $this->newTables()->getExisting() );
	}

	public function testListsTheTablesThatAreMissing(): void {
		$this->assertSame( [ self::MISSING_TABLE ], $this->newTables()->getMissing() );
	}

	public function testDropsTheTables(): void {
		$this->newTables()->drop();

		$this->assertFalse( $this->getDb()->tableExists( self::SCRATCH_TABLE, __METHOD__ ) );
	}

	private function newTables(): NeoWikiTables {
		return new NeoWikiTables( $this->getDb(), [ self::SCRATCH_TABLE, self::MISSING_TABLE ] );
	}

}
