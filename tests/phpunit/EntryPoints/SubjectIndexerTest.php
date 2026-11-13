<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Content\FallbackContent;
use MediaWiki\Revision\RevisionAccessException;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Revision\RevisionSlots;
use ProfessionalWiki\NeoWiki\Application\NullRevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\EntryPoints\SubjectIndexer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyPublishedSubjectIndex;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectPageIndex;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SubjectIndexer
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectsToPublish
 * @group Database
 */
class SubjectIndexerTest extends NeoWikiIntegrationTestCase {

	private const int DELETED_PAGE_ID = 42;
	private const int STUB_PAGE_ID = 43;
	private const string APPROVED_PAGE = 'Page with an approved revision';

	private SpySubjectPageIndex $subjectPageIndex;
	private SpyPublishedSubjectIndex $publishedSubjectIndex;

	protected function setUp(): void {
		parent::setUp();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
		$this->subjectPageIndex = new SpySubjectPageIndex();
		$this->publishedSubjectIndex = new SpyPublishedSubjectIndex();
	}

	public function testIndexesTheSubjectsThePageHolds(): void {
		$revision = $this->createPageWithSubjects( 'Page with indexed subject', TestSubject::build() );

		$this->newIndexer()->indexRevision( $revision );

		$this->assertSame(
			[ $revision->getPageId() => [ TestSubject::ZERO_GUID ] ],
			$this->subjectPageIndex->indexedSubjectsByPageId
		);
	}

	public function testIndexesThePublishedSubjectsOfThePage(): void {
		$revision = $this->createPageWithSubjects( 'Page with published subject', TestSubject::build() );

		$this->newIndexer()->indexRevision( $revision );

		$this->assertSame(
			[ $revision->getPageId() => [ TestSubject::ZERO_GUID ] ],
			$this->publishedSubjectIndex->indexedSubjectsByPageId
		);
	}

	public function testIndexesAPageWithoutSubjectsAsPublishingNone(): void {
		$pageId = $this->insertPage( 'Plain page', 'Just wikitext, no subjects.' )['id'];
		$revision = $this->getServiceContainer()->getRevisionStore()->getRevisionByPageId( $pageId );
		$this->assertInstanceOf( RevisionRecord::class, $revision );

		$this->newIndexer()->indexRevision( $revision );

		$this->assertSame( [ $pageId => [] ], $this->publishedSubjectIndex->indexedSubjectsByPageId );
	}

	public function testLeavesTheIndexesAloneWhenTheSubjectSlotDoesNotHoldSubjectContent(): void {
		// An unregistered content model hands back FallbackContent rather than SubjectContent. Indexing the
		// page as holding no Subjects would drop the ones it does hold.
		$revision = $this->newRevisionWithSlotContent(
			self::STUB_PAGE_ID,
			new FallbackContent( '{"subjects":{}}', 'unregistered-model' )
		);

		$this->newIndexer()->indexRevision( $revision );

		$this->assertSame( [], $this->subjectPageIndex->indexedSubjectsByPageId );
		$this->assertSame( [], $this->publishedSubjectIndex->indexedSubjectsByPageId );
		$this->assertSame( [], $this->publishedSubjectIndex->removedPageIds );
	}

	public function testPropagatesReadFailureWhenSubjectSlotIsPresentButUnreadable(): void {
		$slots = $this->createStub( RevisionSlots::class );
		$slots->method( 'getContent' )->willThrowException( new RevisionAccessException( 'blob read failed' ) );

		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'getPageId' )->willReturn( 42 );
		$revision->method( 'hasSlot' )->willReturn( true );
		$revision->method( 'getSlots' )->willReturn( $slots );

		$this->expectException( RevisionAccessException::class );

		$this->newIndexer()->indexRevision( $revision );
	}

	public function testUnindexingAPageRemovesItFromBothIndexes(): void {
		$this->newIndexer()->unindexPage( new PageId( self::DELETED_PAGE_ID ) );

		$this->assertSame( [ self::DELETED_PAGE_ID ], $this->subjectPageIndex->removedPageIds );
		$this->assertSame( [ self::DELETED_PAGE_ID ], $this->publishedSubjectIndex->removedPageIds );
	}

	public function testIndexesThePublishedSubjectsOfTheRevisionThePolicyPublishes(): void {
		$approved = $this->createPageWithSubjects( self::APPROVED_PAGE, TestSubject::build() );
		$draft = $this->createPageWithSubjects( self::APPROVED_PAGE );

		$this->newIndexerWithPolicy( FixedRevisionPolicy::publishing( $approved ) )->indexRevision( $draft );

		$this->assertSame(
			[ $draft->getPageId() => [ TestSubject::ZERO_GUID ] ],
			$this->publishedSubjectIndex->indexedSubjectsByPageId,
			'the approved revision holds a Subject the draft removed'
		);
	}

	public function testIndexesTheSubjectsOfTheGivenRevisionWhenAnotherIsPublished(): void {
		$approved = $this->createPageWithSubjects( self::APPROVED_PAGE, TestSubject::build() );
		$draft = $this->createPageWithSubjects( self::APPROVED_PAGE );

		$this->newIndexerWithPolicy( FixedRevisionPolicy::publishing( $approved ) )->indexRevision( $draft );

		$this->assertSame(
			[ $draft->getPageId() => [] ],
			$this->subjectPageIndex->indexedSubjectsByPageId,
			'the index addresses the Subjects the page now holds, not the ones it publishes'
		);
	}

	public function testRemovesAPageThatPublishesNothingFromThePublishedSubjectIndex(): void {
		$revision = $this->createPageWithSubjects( 'Page whose approval was revoked', TestSubject::build() );

		$this->newIndexerWithPolicy( FixedRevisionPolicy::publishingNothing() )->indexRevision( $revision );

		$this->assertSame( [ $revision->getPageId() ], $this->publishedSubjectIndex->removedPageIds );
		$this->assertSame( [], $this->publishedSubjectIndex->indexedSubjectsByPageId );
	}

	public function testIndexesTheSubjectsOfAPageThatPublishesNothing(): void {
		$revision = $this->createPageWithSubjects( 'Page whose draft adds a subject', TestSubject::build() );

		$this->newIndexerWithPolicy( FixedRevisionPolicy::publishingNothing() )->indexRevision( $revision );

		$this->assertSame(
			[ $revision->getPageId() => [ TestSubject::ZERO_GUID ] ],
			$this->subjectPageIndex->indexedSubjectsByPageId,
			'a withdrawn page is still on the wiki, so its Subjects stay editable'
		);
	}

	public function testLeavesThePublishedSubjectIndexAloneWhenThePublishedRevisionHoldsNoSubjectContent(): void {
		$revision = $this->createPageWithSubjects( 'Page with an unreadable approved revision', TestSubject::build() );
		$published = $this->newRevisionWithSlotContent(
			$revision->getPageId(),
			new FallbackContent( '{"subjects":{}}', 'unregistered-model' )
		);

		$this->newIndexerWithPolicy( FixedRevisionPolicy::publishing( $published ) )->indexRevision( $revision );

		$this->assertSame( [], $this->publishedSubjectIndex->indexedSubjectsByPageId );
		$this->assertSame( [], $this->publishedSubjectIndex->removedPageIds );
	}

	public function testPublishesTheApprovedRevisionWhenTheGivenOneDoesNotHoldSubjectContent(): void {
		$approved = $this->createPageWithSubjects( self::APPROVED_PAGE, TestSubject::build() );
		$draft = $this->newRevisionWithSlotContent(
			$approved->getPageId(),
			new FallbackContent( '{"subjects":{}}', 'unregistered-model' )
		);

		$this->newIndexerWithPolicy( FixedRevisionPolicy::publishing( $approved ) )->indexRevision( $draft );

		$this->assertSame(
			[ $approved->getPageId() => [ TestSubject::ZERO_GUID ] ],
			$this->publishedSubjectIndex->indexedSubjectsByPageId
		);
		$this->assertSame( [], $this->subjectPageIndex->indexedSubjectsByPageId, 'the index should be left alone' );
	}

	/**
	 * @dataProvider subjectJsonThatDoesNotDeserializeProvider
	 */
	public function testLeavesThePublishedSubjectIndexAloneWhenThePublishedSubjectsDoNotDeserialize(
		string $subjectJson
	): void {
		$revision = $this->newRevisionWithSlotContent( self::STUB_PAGE_ID, new SubjectContent( $subjectJson ) );

		$this->newIndexer()->indexRevision( $revision );

		$this->assertSame( [], $this->publishedSubjectIndex->indexedSubjectsByPageId );
		$this->assertSame( [], $this->publishedSubjectIndex->removedPageIds );
	}

	public static function subjectJsonThatDoesNotDeserializeProvider(): iterable {
		yield 'a Subject that fails validation' => [ TestSubject::jsonThatDoesNotDeserialize( TestSubject::ZERO_GUID ) ];
		yield 'a Subject that is not an object' => [
			'{"mainSubject":"' . TestSubject::ZERO_GUID . '","subjects":{"' . TestSubject::ZERO_GUID . '":1}}',
		];
	}

	private function newIndexer(): SubjectIndexer {
		return $this->newIndexerWithPolicy( new NullRevisionPolicy() );
	}

	private function newIndexerWithPolicy( RevisionPolicy $policy ): SubjectIndexer {
		return new SubjectIndexer(
			$this->subjectPageIndex,
			$this->publishedSubjectIndex,
			$policy,
			$this->getServiceContainer()->getTitleFormatter()
		);
	}

}
