<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Persistence\DeletedSubjectPageIdsLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseDeletedSubjectPageIdsLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseDeletedSubjectPageIdsLookup
 * @group Database
 */
class DatabaseDeletedSubjectPageIdsLookupTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->markPageTableAsUsed();
	}

	public function testFindsTheSubjectPageThatNoLongerExists(): void {
		$this->createPageWithSubjects( 'Surviving page before', TestSubject::build() );
		$deleted = $this->createPageWithSubjects( 'Deleted page', TestSubject::build() );
		$this->createPageWithSubjects( 'Surviving page after', TestSubject::build() );

		$this->deletePageByName( 'Deleted page' );

		$this->assertSame( [ $deleted->getPageId() ], $this->newLookup()->getDeletedSubjectPageIds() );
	}

	public function testUndeletedPageIsNoLongerReported(): void {
		$this->createPageWithSubjects( 'Restored page', TestSubject::build() );

		$this->deletePageByName( 'Restored page' );
		$this->undeletePageByName( 'Restored page' );

		$this->assertSame( [], $this->newLookup()->getDeletedSubjectPageIds() );
	}

	public function testDeletedPageWithoutSubjectsIsNotReported(): void {
		// The subject page is what registers the subject slot role, without which the lookup short-circuits
		// and this test would pass no matter what the query selects.
		$subjectPage = $this->createPageWithSubjects( 'Deleted subject page', TestSubject::build() );
		$this->editPage( 'Plain page', 'A page carrying no Subjects.' );

		$this->deletePageByName( 'Plain page' );
		$this->deletePageByName( 'Deleted subject page' );

		$this->assertSame( [ $subjectPage->getPageId() ], $this->newLookup()->getDeletedSubjectPageIds() );
	}

	/**
	 * A page can be archived and present at the same time: restoring only some of a deleted page's
	 * revisions brings the page back while the rest stay in the archive. Purging it from the graph then
	 * would delete a live page's data, so the lookup must not report it.
	 */
	public function testPageThatIsBothArchivedAndPresentIsNotReported(): void {
		$revision = $this->createPageWithSubjects( 'Partly restored page', TestSubject::build() );

		$this->archiveRevisionOfExistingPage( $revision );

		$this->assertSame( [], $this->newLookup()->getDeletedSubjectPageIds() );
	}

	private function newLookup(): DeletedSubjectPageIdsLookup {
		return new DatabaseDeletedSubjectPageIdsLookup(
			$this->getDb(),
			MediaWikiServices::getInstance()->getSlotRoleStore()
		);
	}

	private function deletePageByName( string $pageName ): void {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle( Title::newFromText( $pageName ) );
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage(
			$page,
			$this->getTestSysop()->getUser()
		);

		$this->assertStatusGood( $deletePage->deleteUnsafe( 'test deletion' ) );
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
			->select( [ 'rev_actor', 'rev_comment_id', 'rev_timestamp', 'rev_len', 'rev_sha1' ] )
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
				'ar_sha1' => $row->rev_sha1,
				'ar_minor_edit' => 0,
				'ar_deleted' => 0,
			] )
			->caller( __METHOD__ )
			->execute();
	}

}
