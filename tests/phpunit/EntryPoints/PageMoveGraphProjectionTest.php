<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Moving a page keeps its graph node's title-derived properties in sync. This needs no
 * dedicated move hook: core creates a null revision on the new title for every move and
 * fires RevisionFromEditComplete for it, which rewrites the page node.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onRevisionFromEditComplete
 * @group Database
 */
class PageMoveGraphProjectionTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
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

	private function movePage( string $from, string $to ): void {
		$movePage = MediaWikiServices::getInstance()->getMovePageFactory()->newMovePage(
			Title::newFromText( $from ),
			Title::newFromText( $to )
		);

		$status = $movePage->move( $this->getTestSysop()->getUser(), 'test move', false );
		$this->assertStatusGood( $status );

		DeferredUpdates::doUpdates();
	}

}
