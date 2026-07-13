<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use StatusValue;

/**
 * Guards the hook-facing write path at the wiring level: an edit and a delete must survive a backend
 * that is down, and a failing backend must not starve the backends composed after it. Undeletes run
 * through the same getStoreContentUC() wiring, so they are covered by the same guard.
 *
 * The counterpart of RebuildPathPropagatesProjectionFailureTest, which guards the opposite need on
 * the maintenance path. Both are wiring tests on purpose: the decorator has its own unit tests, but
 * without these nothing would catch NeoWikiExtension being rewired to the propagating composite,
 * which would silently restore the outage-hard-fails-the-edit behavior that #1028 removed.
 *
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\FailureIsolatingGraphDatabasePlugin
 * @group Database
 */
class HookPathIsolatesProjectionFailureTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	protected function tearDown(): void {
		parent::tearDown();
		// The tests rebuild the singleton with extra plugins registered; reset it so later tests get a
		// clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	public function testEditCommitsWhenABackendIsDown(): void {
		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin() );

		$revision = $this->createPageWithSubjects( 'Edit during outage', TestSubject::build() );

		$this->assertNotNull( $revision, 'the edit should still commit while a backend is unreachable' );
	}

	public function testDeleteCommitsWhenABackendIsDown(): void {
		$this->createPageWithSubjects( 'Delete during outage', TestSubject::build() );
		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin() );

		$status = $this->deletePageByName( 'Delete during outage' );

		$this->assertStatusGood( $status, 'the deletion should still commit while a backend is unreachable' );
	}

	public function testFailingBackendDoesNotStarveTheBackendsAfterIt(): void {
		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin(), $spy );

		$revision = $this->createPageWithSubjects( 'Outage isolation page', TestSubject::build() );
		$this->assertNotNull( $revision );

		$this->assertSame(
			[ $revision->getPageId() ],
			array_map( static fn ( Page $page ) => $page->getId()->id, $spy->savedPages ),
			'a backend composed after the failing one should still receive savePage'
		);
	}

	private function deletePageByName( string $pageName ): StatusValue {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage(
			$page,
			$this->getTestSysop()->getUser()
		);

		$status = $deletePage->deleteUnsafe( 'test deletion' );

		DeferredUpdates::doUpdates();

		return $status;
	}

}
