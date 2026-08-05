<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseDeletedPageIdsLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseDeletedPageIdsLookup
 * @group Database
 */
class DatabaseDeletedPageIdsLookupTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testFindsThePageThatNoLongerExists(): void {
		$this->createPageWithSubjects( 'Surviving page before', TestSubject::build() );
		$deleted = $this->createPageWithSubjects( 'Deleted page', TestSubject::build() );
		$this->createPageWithSubjects( 'Surviving page after', TestSubject::build() );

		$this->deletePageByName( 'Deleted page' );

		$this->assertSame( [ $deleted->getPageId() ], $this->newLookup()->getDeletedPageIdsAfter( 0, 100 ) );
	}

	public function testUndeletedPageIsNoLongerReported(): void {
		$this->createPageWithSubjects( 'Restored page', TestSubject::build() );

		$this->deletePageByName( 'Restored page' );
		$this->undeletePageByName( 'Restored page' );

		$this->assertSame( [], $this->newLookup()->getDeletedPageIdsAfter( 0, 100 ) );
	}

	public function testFindsDeletedPagesWithAndWithoutSubjects(): void {
		$subjectPage = $this->createPageWithSubjects( 'Deleted subject page', TestSubject::build() );
		$plainPage = $this->editPage( 'Deleted plain page', 'A page carrying no Subjects.' )->getNewRevision();

		$this->deletePageByName( 'Deleted subject page' );
		$this->deletePageByName( 'Deleted plain page' );

		$this->assertSame( [ $subjectPage->getPageId() ], $this->newLookup()->getDeletedPageIdsAfter( 0, 100 ) );
	}

	/**
	 * A page can be archived and present at the same time: restoring only some of a deleted page's
	 * revisions brings the page back while the rest stay in the archive. Purging it from the graph then
	 * would delete a live page's data, so the lookup must not report it.
	 */
	public function testPageThatIsBothArchivedAndPresentIsNotReported(): void {
		$revision = $this->createPageWithSubjects( 'Partly restored page', TestSubject::build() );

		$this->archiveRevisionOfExistingPage( $revision );

		$this->assertSame( [], $this->newLookup()->getDeletedPageIdsAfter( 0, 100 ) );
	}

	public function testWalksOnePageAtATimeFromTheCursor(): void {
		$first = $this->createPageWithSubjects( 'First deleted page', TestSubject::build() );
		$second = $this->createPageWithSubjects( 'Second deleted page', TestSubject::build() );
		$this->deletePageByName( 'First deleted page' );
		$this->deletePageByName( 'Second deleted page' );

		$lookup = $this->newLookup();

		$this->assertSame( [ $first->getPageId() ], $lookup->getDeletedPageIdsAfter( 0, 1 ) );
		$this->assertSame(
			[ $second->getPageId() ],
			$lookup->getDeletedPageIdsAfter( $first->getPageId(), 1 )
		);
		$this->assertSame( [], $lookup->getDeletedPageIdsAfter( $second->getPageId(), 1 ) );
	}

	/**
	 * A deleted page leaves one archive row per revision it had, so paging without making the ids distinct
	 * first would fill a batch with one page repeated and never reach the next.
	 */
	public function testAPageWithSeveralArchivedRevisionsFillsOneSlotOfTheBatch(): void {
		$firstRevision = $this->createPageWithSubjects( 'Twice edited page', TestSubject::build() );
		$secondRevision = $this->createPageWithSubjects(
			'Twice edited page',
			TestSubject::build( label: 'A different label' )
		);
		// The premise: two archived revisions of one page. An edit storing identical content is a null
		// edit, which creates no revision and would leave this testing nothing.
		$this->assertNotSame( $firstRevision?->getId(), $secondRevision?->getId() );

		$next = $this->createPageWithSubjects( 'Later page', TestSubject::build() );
		$this->deletePageByName( 'Twice edited page' );
		$this->deletePageByName( 'Later page' );

		$this->assertSame( 2, $this->newLookup()->countDeletedPages() );
		$this->assertSame( $next->getPageId(), $this->newLookup()->getDeletedPageIdsAfter( 0, 2 )[1] );
	}

	public function testAWikiThatLostNoSubjectPageHasNoneToRemove(): void {
		$this->createPageWithSubjects( 'Surviving page', TestSubject::build() );

		$this->assertSame( 0, $this->newLookup()->countDeletedPages() );
	}

	public function testDrainsEveryBatch(): void {
		// The generator pages the archive in fixed-size keyset batches. With more deleted pages than one
		// batch, it must keep querying past the first batch and yield every page exactly once — a single
		// truncated batch would drop the tail.
		$pageIds = $this->archiveRowsForPagesThatNoLongerExist( DatabaseDeletedPageIdsLookup::BATCH_SIZE + 20 );

		$this->assertSame( $pageIds, $this->getDeletedPageIds() );
	}

	/**
	 * @return int[]
	 */
	private function getDeletedPageIds(): array {
		$lookup = new DatabaseDeletedPageIdsLookup( $this->getDb() );

		return iterator_to_array( $lookup->getDeletedPageIds(), false );
	}

	/**
	 * Writes one archive row per page for page ids no page table row uses, which is the state deleting
	 * that many pages would leave behind. Written straight to the archive table: creating and deleting
	 * hundreds of real pages costs far more than the rows it produces.
	 *
	 * @return int[] The page ids, in ascending order.
	 */
	private function archiveRowsForPagesThatNoLongerExist( int $count ): array {
		$firstPageId = (int)$this->getDb()->newSelectQueryBuilder()
			->select( 'MAX(page_id)' )
			->from( 'page' )
			->caller( __METHOD__ )
			->fetchField() + 1;

		$pageIds = range( $firstPageId, $firstPageId + $count - 1 );

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'archive' )
			->rows( array_map(
				fn ( int $pageId ): array => [
					'ar_page_id' => $pageId,
					'ar_rev_id' => $pageId,
					'ar_namespace' => NS_HELP,
					'ar_title' => 'Bulk deleted page ' . $pageId,
					'ar_actor' => 1,
					'ar_comment_id' => 1,
					'ar_timestamp' => $this->getDb()->timestamp(),
					'ar_len' => 0,
					'ar_minor_edit' => 0,
					'ar_deleted' => 0,
				],
				$pageIds
			) )
			->caller( __METHOD__ )
			->execute();

		return $pageIds;
	}

	private function undeletePageByName( string $pageName ): void {
		$undeletePage = MediaWikiServices::getInstance()->getUndeletePageFactory()->newUndeletePage(
			MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) ),
			$this->getTestSysop()->getUser()
		);

		$this->assertStatusGood( $undeletePage->undeleteUnsafe( 'test undeletion' ) );
	}

	/**
	 * Archives one revision of a page that still exists, which is the state a partial undeletion leaves
	 * behind. Written straight to the archive table: MediaWiki only reaches this state through a selective
	 * restore, which is far more setup than the row it produces.
	 */
	private function archiveRevisionOfExistingPage( RevisionRecord $revision ): void {
		$row = $this->getDb()->newSelectQueryBuilder()
			->select( [ 'rev_actor', 'rev_comment_id', 'rev_timestamp', 'rev_len' ] )
			->from( 'revision' )
			->where( [ 'rev_id' => $revision->getId() ] )
			->caller( __METHOD__ )
			->fetchRow();

		$this->getDb()->newInsertQueryBuilder()
			->insertInto( 'archive' )
			->row( [
				'ar_page_id' => $revision->getPageId(),
				'ar_rev_id' => $revision->getId(),
				'ar_namespace' => NS_MAIN,
				'ar_title' => Title::newFromText( 'Partly restored page' )->getDBkey(),
				'ar_actor' => $row->rev_actor,
				'ar_comment_id' => $row->rev_comment_id,
				'ar_timestamp' => $row->rev_timestamp,
				'ar_len' => $row->rev_len,
				'ar_minor_edit' => 0,
				'ar_deleted' => 0,
			] )
			->caller( __METHOD__ )
			->execute();
	}

}
