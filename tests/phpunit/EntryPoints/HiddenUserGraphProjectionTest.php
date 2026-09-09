<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MediaWikiServices;
use MediaWiki\User\User;
use ProfessionalWiki\NeoWiki\EntryPoints\Jobs\RebuildLastEditorPagesJob;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;

/**
 * Hiding a user hides their name on every revision they made, by a write straight to the revision
 * rows that fires no revision hook of its own — so without this the graph would go on naming them as
 * the last editor of every page they last edited, readable by anyone the Cypher endpoint is open to
 * (#1246). The RevisionDelete route to the same disclosure is covered by
 * RevisionVisibilityGraphProjectionTest.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onBlockIpComplete
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onUnblockUserComplete
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\Jobs\RebuildLastEditorPagesJob
 * @covers \ProfessionalWiki\NeoWiki\Application\LastEditorPagesRebuilder
 * @group Database
 */
class HiddenUserGraphProjectionTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		// Core grants hideuser to the suppress group only.
		$this->setGroupPermissions( 'sysop', 'hideuser', true );
	}

	public function testHidingAUserTakesTheirNameOffEveryPageTheyLastEdited(): void {
		$author = $this->getTestUser()->getUser();
		$first = $this->editPageAs( 'First page of a hidden user', $author );
		$second = $this->editPageAs( 'Second page of a hidden user', $author );
		$this->assertSame(
			[ $author->getName(), $author->getName() ],
			[ $this->readLastEditor( $first ), $this->readLastEditor( $second ) ],
			'precondition: the graph names the author of both'
		);

		$this->hideUser( $author );

		$this->assertSame( [ '', '' ], [ $this->readLastEditor( $first ), $this->readLastEditor( $second ) ] );
	}

	public function testShowingAUserAgainPutsTheirNameBack(): void {
		$author = $this->getTestUser()->getUser();
		$pageId = $this->editPageAs( 'Page of a user hidden and shown again', $author );
		$this->hideUser( $author );
		$this->assertSame( '', $this->readLastEditor( $pageId ), 'precondition: the name is gone' );

		$this->showUser( $author );

		$this->assertSame( $author->getName(), $this->readLastEditor( $pageId ) );
	}

	/**
	 * A block that hides nobody withdraws no name, so it must queue no reprojection at all. Asserting
	 * the projected name instead would prove nothing: reprojecting a page whose author is still public
	 * writes the same name back, so the assertion holds either way.
	 */
	public function testAnOrdinaryBlockQueuesNoReprojection(): void {
		$author = $this->getTestUser()->getUser();
		$this->editPageAs( 'Page of a merely blocked user', $author );

		$this->blockUser( $author, hidden: false );

		$this->assertSame( 0, $this->queuedReprojections() );
	}

	public function testHidingAUserQueuesTheReprojection(): void {
		$author = $this->getTestUser()->getUser();
		$this->editPageAs( 'Page of a user about to be hidden', $author );

		$this->blockUser( $author, hidden: true );

		$this->assertSame( 1, $this->queuedReprojections() );
	}

	/**
	 * Which pages get reprojected, rather than what they end up holding. A page someone else has edited
	 * since no longer carries the hidden name, and reprojecting it would write back the name it already
	 * has — so only the set of pages touched can tell a correctly scoped run from an over-broad one.
	 */
	public function testHidingAUserReprojectsOnlyThePagesTheyLastEdited(): void {
		$author = $this->getTestUser()->getUser();
		$stillTheirs = $this->editPageAs( 'Page still headed by the hidden user', $author );
		$this->editPageAs( 'Page taken over from a hidden user', $author );
		$this->editPageAs( 'Page taken over from a hidden user', $this->getTestSysop()->getUser() );

		$store = new SpyGraphDatabasePlugin();
		$this->registerGraphDatabasePlugins( $store );

		$this->hideUser( $author );

		$this->assertSame( [ $stillTheirs ], self::savedPageIds( $store ) );
	}

	private function editPageAs( string $pageName, User $author ): int {
		return $this->editPage( $pageName, 'Edited by ' . $author->getName(), '', NS_MAIN, $author )
			->getNewRevision()
			->getPageId();
	}

	private function hideUser( User $target ): void {
		$this->blockUser( $target, hidden: true );
		$this->runQueuedReprojections();
	}

	private function showUser( User $target ): void {
		$status = MediaWikiServices::getInstance()->getUnblockUserFactory()->newUnblockUser(
			$target,
			$this->getTestSysop()->getUser(),
			'Unblocked in a test'
		)->unblock();

		$this->assertStatusGood( $status );
		$this->runQueuedReprojections();
	}

	/**
	 * Places the block and lets the deferred updates run, which is what files the reprojection, but
	 * stops short of running it — so a caller can either count what was queued or run it.
	 */
	private function blockUser( User $target, bool $hidden ): void {
		$status = MediaWikiServices::getInstance()->getBlockUserFactory()->newBlockUser(
			$target,
			$this->getTestSysop()->getUser(),
			'infinity',
			'Blocked in a test',
			[ 'isHideUser' => $hidden ]
		)->placeBlock();

		$this->assertStatusGood( $status );
		DeferredUpdates::doUpdates();
	}

	private function runQueuedReprojections(): void {
		// minJobs 0: a block that hides nobody queues nothing, and that case is one of the assertions.
		$this->runJobs( [ 'minJobs' => 0 ] );
	}

	private function queuedReprojections(): int {
		return $this->getServiceContainer()
			->getJobQueueGroup()
			->get( RebuildLastEditorPagesJob::TYPE )
			->getSize();
	}

	private function readLastEditor( int $pageId ): ?string {
		$result = $this->readGraph(
			'MATCH (page:Page {id: $pageId}) RETURN page.lastEditor AS lastEditor',
			[ 'pageId' => $pageId ]
		);

		return $result->first()->toRecursiveArray()['lastEditor'] ?? null;
	}

}
