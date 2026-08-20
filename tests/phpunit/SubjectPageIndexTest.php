<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\Content;
use MediaWiki\Content\FallbackContent;
use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndex;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndexRebuilder;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use Throwable;

/**
 * The subject -> page index over the page lifecycle: what a wiki operation leaves the index holding,
 * and what a Subject id then resolves to.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndex
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndexRebuilder
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\OnRevisionCreatedHandler
 * @group Database
 */
class SubjectPageIndexTest extends NeoWikiIntegrationTestCase {

	private const FIRST_ID = 's1zz1111111azz1';
	private const SECOND_ID = 's1zz1111111azz2';
	private const THIRD_ID = 's1zz1111111azz3';

	public function testCreatingAPageIndexesTheSubjectsItHolds(): void {
		$pageId = $this->createPageHolding( 'Created page', self::FIRST_ID, self::SECOND_ID );

		$this->assertSame( [ self::FIRST_ID, self::SECOND_ID ], $this->indexedSubjectsOf( $pageId ) );
	}

	public function testAPageWithoutSubjectsIndexesNothing(): void {
		$page = $this->getExistingTestPage( 'Plain page' );

		$this->assertSame( [], $this->indexedSubjectsOf( $page->getId() ) );
	}

	public function testAddingASubjectIndexesIt(): void {
		$pageId = $this->createPageHolding( 'Growing page', self::FIRST_ID );

		$this->createPageHolding( 'Growing page', self::FIRST_ID, self::SECOND_ID );

		$this->assertSame( [ self::FIRST_ID, self::SECOND_ID ], $this->indexedSubjectsOf( $pageId ) );
	}

	public function testRemovingASubjectUnindexesIt(): void {
		$pageId = $this->createPageHolding( 'Shrinking page', self::FIRST_ID, self::SECOND_ID );

		$this->createPageHolding( 'Shrinking page', self::FIRST_ID );

		$this->assertSame( [ self::FIRST_ID ], $this->indexedSubjectsOf( $pageId ) );
	}

	public function testDeletingAPageUnindexesItsSubjects(): void {
		$pageId = $this->createPageHolding( 'Doomed page', self::FIRST_ID );

		$this->deletePageByName( 'Doomed page' );

		$this->assertSame( [], $this->indexedSubjectsOf( $pageId ) );
	}

	/**
	 * Imported revisions reach the index through AfterImportPage, which every import path fires, so an
	 * imported Subject is findable without a rebuild.
	 */
	public function testImportingAPageIndexesTheSubjectsItHolds(): void {
		$this->createPageHolding( 'Exported page', self::FIRST_ID );
		$xml = $this->exportPageToXml( 'Exported page' );
		$this->deletePageByName( 'Exported page' );

		$this->importXml( $xml );

		$identifiers = $this->newLookup()->getPageIdOfSubject( new SubjectId( self::FIRST_ID ) );
		$this->assertNotNull( $identifiers );
		$this->assertSame( 'Exported page', $identifiers->getTitle() );
	}

	/**
	 * The title comes from the page table on every read, so a move leaves the index untouched and the
	 * Subject resolves to where the page now is.
	 */
	public function testMovingAPageLeavesItsSubjectsResolvingToIt(): void {
		$pageId = $this->createPageHolding( 'Before the move', self::FIRST_ID );

		$this->movePage( 'Before the move', 'After the move' );

		$identifiers = $this->newLookup()->getPageIdOfSubject( new SubjectId( self::FIRST_ID ) );
		$this->assertNotNull( $identifiers );
		$this->assertSame( $pageId, $identifiers->getId()->id );
		$this->assertSame( 'After the move', $identifiers->getTitle() );
	}

	/**
	 * Restoring archived revisions onto a page that has since been recreated leaves a newer revision
	 * current, so the page is indexed from what it holds now rather than from the revision restored.
	 */
	public function testPartiallyUndeletingAPageIndexesWhatItCurrentlyHolds(): void {
		$this->createPageHolding( 'Recreated page', self::FIRST_ID );
		$this->deletePageByName( 'Recreated page' );
		$pageId = $this->createPageHolding( 'Recreated page', self::SECOND_ID );

		$this->undeleteArchivedRevisions( 'Recreated page' );

		$this->assertSame( [ self::SECOND_ID ], $this->indexedSubjectsOf( $pageId ) );
	}

	public function testUndeletingADeletedPageIndexesItsSubjectsAgain(): void {
		$pageId = $this->createPageHolding( 'Restored page', self::FIRST_ID );
		$this->deletePageByName( 'Restored page' );

		$this->undeleteArchivedRevisions( 'Restored page' );

		$this->assertSame( [ self::FIRST_ID ], $this->indexedSubjectsOf( $pageId ) );
	}

	/**
	 * The rebuild is the repair path for writes MediaWiki gives no usable hook for, so it indexes a page
	 * whose Subjects nothing told the index about.
	 */
	public function testRebuildingIndexesAPageWrittenWithoutTheHook(): void {
		$pageId = $this->createPageWithoutTellingNeoWiki(
			'Unhooked page',
			$this->subjectSlotJson( self::FIRST_ID )
		);

		$this->rebuildIndex();

		$this->assertSame( [ self::FIRST_ID ], $this->indexedSubjectsOf( $pageId ) );
	}

	/**
	 * A Subject too broken to deserialize is a persisted, supported state, and the index is what gets an
	 * editor to the page holding it — so its id is read from the raw JSON and indexed like any other.
	 */
	public function testRebuildingIndexesASubjectThatDoesNotDeserialize(): void {
		$json = TestSubject::jsonThatDoesNotDeserialize( self::FIRST_ID );
		$this->assertDoesNotDeserialize( $json );

		$pageId = $this->createPageWithoutTellingNeoWiki( 'Broken subject page', $json );

		$this->rebuildIndex();

		$this->assertSame( [ self::FIRST_ID ], $this->indexedSubjectsOf( $pageId ) );
	}

	/**
	 * The walk is batched, so it has to carry on past the first batch and reach every page holding
	 * Subjects — not only the ones the first query returned.
	 */
	public function testRebuildingReachesEveryPageAcrossBatches(): void {
		$firstPageId = $this->createPageWithoutTellingNeoWiki( 'Batched page 1', $this->subjectSlotJson( self::FIRST_ID ) );
		$secondPageId = $this->createPageWithoutTellingNeoWiki( 'Batched page 2', $this->subjectSlotJson( self::SECOND_ID ) );
		$thirdPageId = $this->createPageWithoutTellingNeoWiki( 'Batched page 3', $this->subjectSlotJson( self::THIRD_ID ) );

		$this->rebuildIndex( batchSize: 1 );

		$this->assertSame( [ self::FIRST_ID ], $this->indexedSubjectsOf( $firstPageId ) );
		$this->assertSame( [ self::SECOND_ID ], $this->indexedSubjectsOf( $secondPageId ) );
		$this->assertSame( [ self::THIRD_ID ], $this->indexedSubjectsOf( $thirdPageId ) );
	}

	public function testRebuildingUnindexesAPageDeletedWithoutTheHook(): void {
		$pageId = $this->createPageHolding( 'Silently deleted page', self::FIRST_ID );
		$this->clearHook( 'PageDeleteComplete' );
		$this->deletePageByName( 'Silently deleted page' );

		$this->rebuildIndex();

		$this->assertSame( [], $this->indexedSubjectsOf( $pageId ) );
	}

	public function testRebuildingUnindexesAPageThatLostItsSubjectsWithoutTheHook(): void {
		$pageId = $this->createPageHolding( 'Emptied page', self::FIRST_ID );
		$this->createPageWithoutTellingNeoWiki( 'Emptied page', $this->subjectSlotJson() );

		$this->rebuildIndex();

		$this->assertSame( [], $this->indexedSubjectsOf( $pageId ) );
	}

	/**
	 * A page that lost the slot itself is not reached by the walk at all, so only the sweep can drop
	 * what it used to hold.
	 */
	public function testRebuildingUnindexesAPageThatLostItsSubjectSlotWithoutTheHook(): void {
		$pageId = $this->createPageHolding( 'Slotless page', self::FIRST_ID );
		$this->removeSubjectSlotWithoutTellingNeoWiki( 'Slotless page' );

		$this->rebuildIndex();

		$this->assertSame( [], $this->indexedSubjectsOf( $pageId ) );
	}

	/**
	 * A slot holding content that is not Subject data cannot be read as Subjects, so the page is left as
	 * it is rather than reindexed as holding none — which would drop the Subjects it does hold. The hook
	 * path skips such a page for the same reason.
	 */
	public function testRebuildingLeavesAPageWhoseSlotDoesNotHoldSubjectContentAlone(): void {
		$pageId = $this->createPageHolding( 'Unreadable slot page', self::FIRST_ID );

		$this->runRebuild(
			$this->revisionLookupWithSlotContent( new FallbackContent( '{"subjects":{}}', 'unregistered-model' ) ),
			DatabaseSubjectPageIndexRebuilder::DEFAULT_BATCH_SIZE
		);

		$this->assertSame( [ self::FIRST_ID ], $this->indexedSubjectsOf( $pageId ) );
	}

	/**
	 * A graph rebuild walks the wiki from a replica, so the revision it projects may already have been
	 * superseded. The index is not allowed to lag that way, so reprojecting a store leaves it alone.
	 */
	public function testRebuildingAGraphStoreDoesNotWriteTheIndex(): void {
		$pageId = $this->createPageHolding( 'Reprojected page', self::FIRST_ID );
		$this->emptyIndexOf( $pageId );

		$outcome = NeoWikiExtension::getInstance()
			->newPageRebuilderFor( new SpyGraphDatabasePlugin() )
			->rebuild( Title::newFromText( 'Reprojected page' ) );

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome, 'the page should have been reprojected' );
		$this->assertSame( [], $this->indexedSubjectsOf( $pageId ) );
	}

	private function revisionLookupWithSlotContent( Content $content ): RevisionLookup {
		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'hasSlot' )->willReturn( true );
		$revision->method( 'getContent' )->willReturn( $content );

		$revisionLookup = $this->createStub( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionByPageId' )->willReturn( $revision );

		return $revisionLookup;
	}

	private function emptyIndexOf( int $pageId ): void {
		$this->getDb()->newDeleteQueryBuilder()
			->deleteFrom( DatabaseSubjectPageIndex::TABLE )
			->where( [ 'nwsp_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->execute();

		$this->assertSame( [], $this->indexedSubjectsOf( $pageId ) );
	}

	private function assertDoesNotDeserialize( string $subjectJson ): void {
		try {
			( new SubjectContent( $subjectJson ) )->getPageSubjects();
		} catch ( Throwable ) {
			return;
		}

		$this->fail( 'Expected Subject data too broken to deserialize' );
	}

	private function createPageHolding( string $pageName, string ...$subjectIds ): int {
		$subjects = array_map(
			static fn ( string $id ) => TestSubject::build( id: $id, label: 'Subject ' . $id ),
			$subjectIds
		);

		$revision = $this->createPageWithSubjects(
			$pageName,
			array_shift( $subjects ),
			new SubjectMap( ...$subjects )
		);

		$this->assertNotNull( $revision );

		return $revision->getPageId();
	}

	/**
	 * Saves a revision with NeoWiki's revision hook removed, which is the shape of the write it is not
	 * told about: a history merge that leaves the source page as a redirect.
	 */
	private function createPageWithoutTellingNeoWiki( string $pageName, string $subjectJson ): int {
		$this->clearHook( 'RevisionFromEditComplete' );

		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $pageName ) );

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new TextContent( '' ) );
		$updater->setContent( MediaWikiSubjectRepository::SLOT_NAME, new SubjectContent( $subjectJson ) );

		$revision = $updater->saveRevision( CommentStoreComment::newUnsavedComment( 'unhooked write' ) );
		$this->assertNotNull( $revision );

		return $revision->getPageId();
	}

	private function removeSubjectSlotWithoutTellingNeoWiki( string $pageName ): void {
		$this->clearHook( 'RevisionFromEditComplete' );

		$updater = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( $pageName ) )
			->newPageUpdater( $this->getTestSysop()->getUser() );

		$updater->removeSlot( MediaWikiSubjectRepository::SLOT_NAME );

		$this->assertNotNull(
			$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'unhooked slot removal' ) )
		);
	}

	private function subjectSlotJson( ?string $subjectId = null ): string {
		if ( $subjectId === null ) {
			return SubjectContent::newFromData( PageSubjects::newEmpty() )->getText();
		}

		return SubjectContent::newFromData(
			new PageSubjects( TestSubject::build( id: $subjectId ), new SubjectMap() )
		)->getText();
	}

	private function rebuildIndex( int $batchSize = DatabaseSubjectPageIndexRebuilder::DEFAULT_BATCH_SIZE ): void {
		$this->runRebuild( $this->getServiceContainer()->getRevisionLookup(), $batchSize );
	}

	private function runRebuild( RevisionLookup $revisionLookup, int $batchSize ): void {
		$rebuilder = new DatabaseSubjectPageIndexRebuilder( $this->getDb(), $revisionLookup, $batchSize );

		iterator_to_array( $rebuilder->rebuild() );
	}

	private function newLookup(): DatabasePageIdentifiersLookup {
		return new DatabasePageIdentifiersLookup(
			$this->getDb(),
			$this->getServiceContainer()->getTitleFormatter()
		);
	}

	/**
	 * @return string[] The Subject ids the index holds for the page, in id order.
	 */
	private function indexedSubjectsOf( int $pageId ): array {
		return $this->getDb()->newSelectQueryBuilder()
			->select( 'nwsp_subject_id' )
			->from( DatabaseSubjectPageIndex::TABLE )
			->where( [ 'nwsp_page_id' => $pageId ] )
			->orderBy( 'nwsp_subject_id' )
			->caller( __METHOD__ )
			->fetchFieldValues();
	}

	private function movePage( string $from, string $to ): void {
		$this->assertStatusGood(
			$this->getServiceContainer()->getMovePageFactory()->newMovePage(
				Title::newFromText( $from ),
				Title::newFromText( $to )
			)->move( $this->getTestSysop()->getUser(), 'test move', false )
		);
	}

	private function undeleteArchivedRevisions( string $pageName ): void {
		$this->assertStatusGood(
			$this->getServiceContainer()->getUndeletePageFactory()->newUndeletePage(
				MediaWikiServices::getInstance()->getWikiPageFactory()
					->newFromTitle( Title::newFromText( $pageName ) )->getTitle()->toPageIdentity(),
				$this->getTestSysop()->getAuthority()
			)->undeleteUnsafe( 'test restore' )
		);
	}

}
