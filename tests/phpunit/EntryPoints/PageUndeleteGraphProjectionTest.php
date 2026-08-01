<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Restoring a page from the archive projects the page as it stands afterwards — once, from its current
 * revision. Restoring archived revisions onto a page that exists must leave the live page's data alone:
 * projecting each restored revision in turn would write the page as of a revision that is not current,
 * which for a stale revision without Subjects means wiping the live page's Subjects from the graph.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onPageUndeleteComplete
 * @group Database
 */
class PageUndeleteGraphProjectionTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testRestoringOldHistoryKeepsTheSubjectsOfTheLivePage(): void {
		$this->editPage( 'Recreated page', 'A first life, with no Subjects.' );
		$this->deletePageByName( 'Recreated page' );

		$revision = $this->createPageWithSubjects( 'Recreated page', TestSubject::build() );

		$this->undeletePageByName( 'Recreated page' );

		$this->assertSame(
			1,
			$this->countSubjectsOfPage( $revision->getPageId() ),
			'restoring the archived history must not project the page as its stale revision left it'
		);
		$this->assertSame( 'Recreated page', $this->readPageNodeName( $revision->getPageId() ) );
	}

	public function testRestoringADeletedPageWithSubjectsBringsBackItsNodeAndSubjects(): void {
		$revision = $this->createPageWithSubjects( 'Deleted subject page', TestSubject::build() );
		$this->deletePageByName( 'Deleted subject page' );
		$this->assertSame( 0, $this->countPageNodes( $revision->getPageId() ), 'precondition: the node is gone' );

		$this->undeletePageByName( 'Deleted subject page' );

		$this->assertSame( 1, $this->countPageNodes( $revision->getPageId() ) );
		$this->assertSame( 1, $this->countSubjectsOfPage( $revision->getPageId() ) );
	}

	public function testRestoringADeletedPageWithoutSubjectsBringsBackItsNode(): void {
		$pageId = $this->editPage( 'Deleted plain page', 'No Subjects here.' )->getNewRevision()->getPageId();
		$this->deletePageByName( 'Deleted plain page' );
		$this->assertSame( 0, $this->countPageNodes( $pageId ), 'precondition: the node is gone' );

		$this->undeletePageByName( 'Deleted plain page' );

		$this->assertSame( 1, $this->countPageNodes( $pageId ) );
		$this->assertSame( 'Deleted plain page', $this->readPageNodeName( $pageId ) );
	}

	private function countPageNodes( int $pageId ): int {
		return $this->readGraph(
			'MATCH (page:Page {id: $pageId}) RETURN count(page) AS count',
			[ 'pageId' => $pageId ]
		)->first()->toRecursiveArray()['count'];
	}

	private function countSubjectsOfPage( int $pageId ): int {
		return $this->readGraph(
			'MATCH (:Page {id: $pageId})-[:HasSubject]->(subject) RETURN count(subject) AS count',
			[ 'pageId' => $pageId ]
		)->first()->toRecursiveArray()['count'];
	}

	private function deletePageByName( string $pageName ): void {
		$this->deletePage(
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) ),
			'test deletion'
		);

		DeferredUpdates::doUpdates();
	}

	private function undeletePageByName( string $pageName ): void {
		$undeletePage = MediaWikiServices::getInstance()->getUndeletePageFactory()->newUndeletePage(
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) ),
			$this->getTestSysop()->getUser()
		);

		$this->assertStatusGood( $undeletePage->undeleteUnsafe( 'test undeletion' ) );
		DeferredUpdates::doUpdates();
	}

}
