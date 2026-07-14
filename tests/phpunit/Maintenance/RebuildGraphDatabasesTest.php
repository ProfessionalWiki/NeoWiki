<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Maintenance\RebuildGraphDatabases;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;

// The maintenance script is not PSR-4 autoloadable (it lives outside src/), so load it explicitly.
// Its RUN_MAINTENANCE_IF_MAIN guard is a no-op under PHPUnit, so this does not execute the script.
require_once __DIR__ . '/../../../maintenance/RebuildGraphDatabases.php';

/**
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\RebuildGraphDatabases
 * @group Database
 */
class RebuildGraphDatabasesTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	protected function tearDown(): void {
		parent::tearDown();
		// The test rebuilds the singleton with a spy plugin registered; reset it so later tests get a
		// clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	public function testRebuildRemovesADeletedSubjectPageFromTheGraph(): void {
		$this->createPageWithSubjects( 'Surviving page before', TestSubject::build() );
		$deleted = $this->createPageWithSubjects( 'Deleted during outage', TestSubject::build() );
		$this->createPageWithSubjects( 'Surviving page after', TestSubject::build() );

		$this->deletePageByName( 'Deleted during outage' );

		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $spy );

		$this->runRebuild();

		$this->assertSame(
			[ $deleted->getPageId() ],
			array_map( static fn ( PageId $pageId ) => $pageId->id, $spy->deletedPageIds ),
			'the rebuild should remove exactly the page MediaWiki no longer has'
		);
	}

	private function runRebuild(): void {
		ob_start();
		try {
			( new RebuildGraphDatabases() )->execute();
		} finally {
			ob_end_clean();
		}
	}

	private function deletePageByName( string $pageName ): void {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage(
			$page,
			$this->getTestSysop()->getUser()
		);

		$this->assertStatusGood( $deletePage->deleteUnsafe( 'test deletion' ) );
	}

}
