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

/**
 * Moving a page keeps its graph node in sync with its new title. This needs no dedicated move hook:
 * core creates a null revision on the new title for every move and fires RevisionFromEditComplete for
 * it, which rewrites the page node, and a redirect left behind is a new page that is projected when it
 * is created. The write-count tests pin that, so a move cannot start projecting a page twice or
 * deleting nodes.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onRevisionFromEditComplete
 * @group Database
 */
class PageMoveGraphProjectionTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	protected function tearDown(): void {
		parent::tearDown();
		// The write-count tests rebuild the singleton with a spy plugin registered; reset it so later
		// tests get a clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	public function testMovingPageUpdatesGraphNodeName(): void {
		$revision = $this->createPageWithSubjects( 'Original move source', TestSubject::build() );
		$pageId = $revision->getPageId();

		$this->movePage( 'Original move source', 'Renamed move target' );

		$this->assertSame(
			'Renamed move target',
			$this->readPageNodeName( $pageId )
		);
	}

	public function testMovingPageToAnotherNamespaceUpdatesNamespaceId(): void {
		$revision = $this->createPageWithSubjects( 'Namespace move source', TestSubject::build() );
		$pageId = $revision->getPageId();

		$this->movePage( 'Namespace move source', 'Help:Namespace move target' );

		$this->assertSame(
			NS_HELP,
			$this->readPageNodeNamespaceId( $pageId )
		);
	}

	public function testMovingPageWithoutSubjectsUpdatesItsGraphNode(): void {
		$pageId = $this->insertPage( 'Subjectless move source', 'No subjects here.' )['id'];

		$this->movePage( 'Subjectless move source', 'Help:Subjectless move target' );

		$this->assertSame( 'Help:Subjectless move target', $this->readPageNodeName( $pageId ) );
		$this->assertSame( NS_HELP, $this->readPageNodeNamespaceId( $pageId ) );
	}

	public function testRedirectLeftBehindByAMoveGetsItsOwnNode(): void {
		$this->insertPage( 'Redirect leaving move source', 'No subjects here.' );

		$this->movePage( 'Redirect leaving move source', 'Redirect leaving move target', createRedirect: true );

		$redirectPageId = Title::newFromText( 'Redirect leaving move source' )->getArticleID();
		$this->assertNotSame( 0, $redirectPageId, 'precondition: the move left a redirect page behind' );
		$this->assertSame( 'Redirect leaving move source', $this->readPageNodeName( $redirectPageId ) );
	}

	public function testMoveProjectsTheMovedPageOnceAndRemovesNothing(): void {
		$pageId = $this->createPageWithSubjects( 'Write count move source', TestSubject::build() )->getPageId();

		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $spy );

		$this->movePage( 'Write count move source', 'Write count move target' );

		$this->assertSame(
			[ $pageId ],
			self::savedPageIds( $spy ),
			'a move should project the moved page exactly once'
		);
		$this->assertSame( [], $spy->deletedPageIds );
	}

	public function testMoveLeavingARedirectProjectsBothPagesOnce(): void {
		$movedPageId = $this->insertPage( 'Redirect write count source', 'No subjects here.' )['id'];

		$spy = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $spy );

		$this->movePage( 'Redirect write count source', 'Redirect write count target', createRedirect: true );

		$this->assertSame(
			[ $movedPageId, Title::newFromText( 'Redirect write count source' )->getArticleID() ],
			self::savedPageIds( $spy ),
			'the moved page and the redirect left behind should each be projected exactly once'
		);
		$this->assertSame( [], $spy->deletedPageIds );
	}

	/**
	 * @return int[]
	 */
	private function movePage( string $from, string $to, bool $createRedirect = false ): void {
		$movePage = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage(
			Title::newFromText( $from ),
			Title::newFromText( $to )
		);

		$status = $movePage->move( $this->getTestSysop()->getUser(), 'test move', $createRedirect );
		$this->assertStatusGood( $status );

		DeferredUpdates::doUpdates();
	}

}
