<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Uninstall;

use MediaWiki\Content\TextContent;
use MediaWiki\Deferred\DeferredUpdates;
use MediaWiki\MainConfigNames;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionStore;
use MediaWiki\Revision\SlotRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall\SubjectSlotRemover;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectIndexingSearchEngine;
use Wikimedia\ObjectCache\HashBagOStuff;
use Wikimedia\ObjectCache\WANObjectCache;
use Wikimedia\Rdbms\IDBAccessObject;

/**
 * Rewrites the test wiki's revision tables, and reads them back through MediaWiki's revision store.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Uninstall\SubjectSlotRemover
 * @group Database
 */
class SubjectSlotRemoverTest extends NeoWikiIntegrationTestCase {

	private const string LIVE_PAGE = 'SubjectSlotRemoverTestLive';
	private const string LIVE_TEXT = 'Hello';
	private const string DELETED_PAGE = 'SubjectSlotRemoverTestDeleted';
	private const string DELETED_TEXT = 'Bye';

	public function testNoRevisionKeepsTheSubjectSlot(): void {
		$this->createLivePageWithTwoRevisions();
		$this->createDeletedPage();

		$this->removeSubjectSlots();

		$this->assertSame( 0, $this->countSubjectSlots() );
	}

	public function testRevisionsKeepTheirMainContent(): void {
		$this->createLivePageWithTwoRevisions();

		$this->removeSubjectSlots();

		$revision = $this->revisionStore()->getRevisionByTitle(
			Title::newFromText( self::LIVE_PAGE ),
			0,
			IDBAccessObject::READ_LATEST
		);
		$this->assertSame( self::LIVE_TEXT, $revision->getContent( SlotRecord::MAIN )->serialize() );
	}

	public function testRevisionsDescribeWhatTheyHoldNow(): void {
		$this->createLivePageWithTwoRevisions();

		$this->removeSubjectSlots();

		$this->assertSame(
			[ $this->descriptionOf( self::LIVE_TEXT ), $this->descriptionOf( self::LIVE_TEXT ) ],
			array_map( $this->describe( ... ), $this->liveRevisions() )
		);
	}

	public function testDeletedRevisionsDescribeWhatTheyHoldNow(): void {
		$this->createDeletedPage();

		$this->removeSubjectSlots();

		$this->assertSame(
			[ $this->descriptionOf( self::DELETED_TEXT ) ],
			array_map( $this->describe( ... ), $this->deletedRevisions() )
		);
	}

	public function testPagesThatLostTheirSubjectsAreMarkedAsChanged(): void {
		$this->createLivePageWithTwoRevisions();
		$this->setPageTouched( '20000101000000' );

		$this->removeSubjectSlots();

		$this->assertGreaterThan(
			'20000101000000',
			Title::newFromText( self::LIVE_PAGE )->getTouched( IDBAccessObject::READ_LATEST )
		);
	}

	public function testPagesThatLostTheirSubjectsArePurgedFromTheCdn(): void {
		$this->createLivePageWithTwoRevisions();
		$purgedTitles = [];
		$this->setTemporaryHook(
			'HtmlCacheUpdaterAppendUrls',
			static function ( Title $title ) use ( &$purgedTitles ): void {
				$purgedTitles[] = $title->getPrefixedText();
			}
		);

		$this->removeSubjectSlots();
		DeferredUpdates::doUpdates();

		$this->assertContains( self::LIVE_PAGE, $purgedTitles );
	}

	public function testSearchNoLongerFindsAPageByTheSubjectsItHeld(): void {
		$this->overrideConfigValue( MainConfigNames::SearchType, SpySubjectIndexingSearchEngine::class );
		SpySubjectIndexingSearchEngine::forgetIndexedText();
		$pageId = $this->createPageWithSubjects( self::LIVE_PAGE, TestSubject::build( label: 'Rijksmuseum' ) )->getPageId();
		DeferredUpdates::doUpdates();

		$this->removeSubjectSlots();

		$this->assertStringNotContainsString( 'rijksmuseum', SpySubjectIndexingSearchEngine::textIndexedForPage( $pageId ) );
	}

	public function testReportsHowManyRevisionsLostTheSlotAfterEachBatch(): void {
		$this->createLivePageWithTwoRevisions();
		$this->createDeletedPage();

		$this->assertSame( [ 1, 2, 3 ], iterator_to_array( $this->newRemover()->remove(), false ) );
	}

	public function testDoesNothingOnAWikiThatNeverHeldSubjects(): void {
		$this->truncateTable( 'slot_roles' );
		$this->getServiceContainer()->resetServiceForTesting( 'SlotRoleStore' );

		$this->assertSame( [], iterator_to_array( $this->newRemover()->remove(), false ) );
	}

	public function testTheNextRequestDoesNotGetTheCurrentRevisionFromAStaleCache(): void {
		$this->useCachesThatOutliveTheRequest();
		$this->createLivePageWithTwoRevisions();
		$title = Title::newFromText( self::LIVE_PAGE );
		$this->revisionStore()->getKnownCurrentRevision( $title );

		$this->removeSubjectSlots();
		$this->resetServices();

		$this->assertSame(
			strlen( self::LIVE_TEXT ),
			$this->revisionStore()->getKnownCurrentRevision( $title )->getSize()
		);
	}

	public function testTheNextRequestDoesNotGetTheSlotsOfATemplateRevisionFromAStaleCache(): void {
		$this->useCachesThatOutliveTheRequest();
		$revisionId = $this->createPageWithSubjects( 'Template:SubjectSlotRemoverTest', TestSubject::build() )->getId();
		$this->revisionStore()->getRevisionById( $revisionId )->getSlotRoles();

		$this->removeSubjectSlots();
		$this->resetServices();

		$this->assertSame( [ SlotRecord::MAIN ], $this->revisionStore()->getRevisionById( $revisionId )->getSlotRoles() );
	}

	private function createLivePageWithTwoRevisions(): void {
		$this->createPageWithSubjects(
			self::LIVE_PAGE,
			TestSubject::build(),
			mainContent: new TextContent( self::LIVE_TEXT )
		);
		$this->changeSubjectsOfPage( self::LIVE_PAGE, otherSubjects: new SubjectMap( TestSubject::build() ) );
	}

	private function createDeletedPage(): void {
		$this->createPageWithSubjects(
			self::DELETED_PAGE,
			TestSubject::build(),
			mainContent: new TextContent( self::DELETED_TEXT )
		);
		$this->deletePageByName( self::DELETED_PAGE );
	}

	private function removeSubjectSlots(): void {
		iterator_to_array( $this->newRemover()->remove() );
	}

	/**
	 * Batches of one, so that every test also covers moving from one batch to the next.
	 */
	private function newRemover(): SubjectSlotRemover {
		$services = $this->getServiceContainer();

		return new SubjectSlotRemover(
			db: $this->getDb(),
			slotRoleStore: $services->getSlotRoleStore(),
			revisionStore: $services->getRevisionStore(),
			htmlCacheUpdater: $services->getHtmlCacheUpdater(),
			wanCache: $services->getMainWANObjectCache(),
			batchSize: 1
		);
	}

	/**
	 * MediaWiki's tests cache nothing across requests by default, which would hide a stale cache entry.
	 */
	private function useCachesThatOutliveTheRequest(): void {
		$this->setService( 'MainWANObjectCache', new WANObjectCache( [ 'cache' => new HashBagOStuff() ] ) );
	}

	private function revisionStore(): RevisionStore {
		return $this->getServiceContainer()->getRevisionStore();
	}

	/**
	 * @return array{string, int} What a revision holding only this text is described by
	 */
	private function descriptionOf( string $text ): array {
		return [ SlotRecord::base36Sha1( $text ), strlen( $text ) ];
	}

	/**
	 * As MediaWiki reads it, which is from the stored checksum up to 1.45 and from the slots after.
	 *
	 * @return array{string, int}
	 */
	private function describe( RevisionRecord $revision ): array {
		return [ $revision->getSha1(), $revision->getSize() ];
	}

	/**
	 * @return RevisionRecord[]
	 */
	private function liveRevisions(): array {
		$rows = $this->revisionStore()->newSelectQueryBuilder( $this->getDb() )
			->joinComment()
			->where( [ 'rev_page' => Title::newFromText( self::LIVE_PAGE )->getArticleID() ] )
			->orderBy( 'rev_id' )
			->caller( __METHOD__ )
			->fetchResultSet();

		return iterator_to_array( $this->revisionStore()->newRevisionsFromBatch( $rows )->getValue(), false );
	}

	/**
	 * @return RevisionRecord[]
	 */
	private function deletedRevisions(): array {
		$rows = $this->revisionStore()->newArchiveSelectQueryBuilder( $this->getDb() )
			->joinComment()
			->where( [ 'ar_title' => self::DELETED_PAGE ] )
			->caller( __METHOD__ )
			->fetchResultSet();

		$revisions = [];

		foreach ( $rows as $row ) {
			$revisions[] = $this->revisionStore()->newRevisionFromArchiveRow( $row, IDBAccessObject::READ_LATEST );
		}

		return $revisions;
	}

	private function setPageTouched( string $timestamp ): void {
		$this->getDb()->newUpdateQueryBuilder()
			->update( 'page' )
			->set( [ 'page_touched' => $timestamp ] )
			->where( [ 'page_id' => Title::newFromText( self::LIVE_PAGE )->getArticleID() ] )
			->caller( __METHOD__ )
			->execute();
	}

}
