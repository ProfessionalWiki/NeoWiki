<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use MediaWiki\Content\Content;
use MediaWiki\Content\FallbackContent;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSaver;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\PageContentSavingStatus;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;
use RuntimeException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository
 * @group Database
 */
class MediaWikiSubjectRepositoryTest extends NeoWikiIntegrationTestCase {

	public function setUp(): void {
		$this->setUpNeo4j();
	}

	private function newRepository(): MediaWikiSubjectRepository {
		return NeoWikiExtension::getInstance()->newSubjectRepository();
	}

	public function testGetSubjectReturnsNullForUnknownSubject(): void {
		$this->assertNull(
			$this->newRepository()->getSubject(
				new SubjectId( 'sTestMSR1111111' )
			)
		);
	}

	private function createPages(): void {
		$this->truncateTables( [ 'page' ], $this->db );

		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );

		$this->createPageWithSubjects(
			'SubjectRepoTestOne',
			mainSubject: TestSubject::build(
				id: 'sTestMSR1111112',
				label: new SubjectLabel( 'Test subject 2' ),
			),
			childSubjects: new SubjectMap(
				TestSubject::build(
					id: 'sTestMSR1111113',
					label: new SubjectLabel( 'Test subject 3' ),
				),
				TestSubject::build(
					id: 'sTestMSR1111114',
					label: new SubjectLabel( 'Test subject 4' ),
				)
			)
		);

		$this->createPageWithSubjects(
			'SubjectRepoTestTwo',
			mainSubject: TestSubject::build(
				id: 'sTestMSR1111115',
				label: new SubjectLabel( 'Test subject 5' ),
			)
		);

		$this->createPageWithSubjects(
			'SubjectRepoTestThree'
		);
	}

	public function testDeleteSubject(): void {
		$this->createPages();

		$this->newRepository()->deleteSubject(
			new SubjectId( 'sTestMSR1111113' ),
			null
		);

		$this->assertNull(
			$this->newRepository()->getSubject(
				new SubjectId( 'sTestMSR1111113' )
			)
		);
	}

	public function testDeleteSubjectReportsTheRevisionItCreated(): void {
		$this->createPages();

		$status = $this->newRepository()->deleteSubject( new SubjectId( 'sTestMSR1111113' ), null );

		$this->assertSame( PageContentSavingStatus::REVISION_CREATED, $status->status );
	}

	public function testDeleteSubjectForUnknownSubjectReportsNoChanges(): void {
		$this->createPages();

		$status = $this->newRepository()->deleteSubject( new SubjectId( 'sTestMSR1111119' ), null );

		$this->assertSame( PageContentSavingStatus::NO_CHANGES, $status->status );
	}

	/**
	 * The index names a page that carries no Subject slot at all - a page deleted and recreated, or
	 * one whose latest revision dropped the slot. Nothing is there to remove.
	 */
	public function testDeleteSubjectOnAPageWithoutSubjectContentReportsNoChanges(): void {
		$this->createPages();

		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$pageIdentifiersLookup->addIdentifiers(
			new SubjectId( 'sTestMSR1111119' ),
			new PageIdentifiers( new PageId( 999999 ), 'NoSuchPage', 0 )
		);

		$repository = new MediaWikiSubjectRepository(
			pageIdentifiersLookup: $pageIdentifiersLookup,
			revisionLookup: $this->getServiceContainer()->getRevisionLookup(),
			pageContentSaver: NeoWikiExtension::getInstance()->getPageContentSaver(),
			titleFactory: $this->getServiceContainer()->getTitleFactory(),
			contentHandlerFactory: $this->getServiceContainer()->getContentHandlerFactory(),
		);

		$status = $repository->deleteSubject( new SubjectId( 'sTestMSR1111119' ), null );

		$this->assertSame( PageContentSavingStatus::NO_CHANGES, $status->status );
	}

	/**
	 * The index names a page whose slot does not hold the Subject. The delete then rewrites the slot
	 * unchanged, and only the saved status says nothing was removed - which is what the API turns
	 * into its not-found answer.
	 */
	public function testDeleteSubjectMissingFromTheIndexedPageReportsNoChanges(): void {
		$this->createPages();

		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$pageIdentifiersLookup->addIdentifiers(
			new SubjectId( 'sTestMSR1111119' ),
			new PageIdentifiers( $this->getPageId( 'SubjectRepoTestOne' ), 'SubjectRepoTestOne', 0 )
		);

		$repository = new MediaWikiSubjectRepository(
			pageIdentifiersLookup: $pageIdentifiersLookup,
			revisionLookup: $this->getServiceContainer()->getRevisionLookup(),
			pageContentSaver: NeoWikiExtension::getInstance()->getPageContentSaver(),
			titleFactory: $this->getServiceContainer()->getTitleFactory(),
			contentHandlerFactory: $this->getServiceContainer()->getContentHandlerFactory(),
		);

		$status = $repository->deleteSubject( new SubjectId( 'sTestMSR1111119' ), null );

		$this->assertSame( PageContentSavingStatus::NO_CHANGES, $status->status );
	}

	public function testGetMainSubjectReturnsNullForUnknownPage(): void {
		$this->assertNull(
			$this->newRepository()->getMainSubject( new PageId( 404404404 ) )
		);
	}

	public function testGetMainSubjectReturnsNullForPageWithoutSubject(): void {
		$pageId = $this->createPageWithSubjects( 'SubjectRepoTestPageWithSubject' )->getPage()->getId();

		$this->assertNull(
			$this->newRepository()->getMainSubject( new PageId( $pageId ) )
		);
	}

	public function testGetMainSubjectReturnsSubject(): void {
		$pageId = $this->createPageWithSubjects(
			'SubjectRepoTestPageWithSubject',
			mainSubject: TestSubject::build(
				id: 'sTestMSR1111112',
				label: new SubjectLabel( 'Test subject 2' ),
			)
		)->getPage()->getId();

		$this->assertEquals(
			TestSubject::build(
				id: 'sTestMSR1111112',
				label: new SubjectLabel( 'Test subject 2' ),
			),
			$this->newRepository()->getMainSubject( new PageId( $pageId ) )
		);
	}

	public function testGetAndSetPageSubjects(): void {
		$pageId = new PageId(
			$this->createPageWithSubjects( 'SubjectRepoTestPageWithSubject' )->getPage()->getId()
		);

		$repo = $this->newRepository();
		$subjects = $repo->getSubjectsByPageId( $pageId );

		$subjects->setMainSubject(
			TestSubject::build(
				id: 'sTestMSR1111112',
				label: new SubjectLabel( 'Test subject 2' ),
			)
		);

		$repo->savePageSubjects( $subjects, $pageId );

		$this->assertEquals(
			TestSubject::build(
				id: 'sTestMSR1111112',
				label: new SubjectLabel( 'Test subject 2' ),
			),
			$repo->getMainSubject( $pageId )
		);
	}

	public function testGetPageSubjectsReturnsEmptySubjectMapForUnknownPage(): void {
		$this->assertEquals(
			PageSubjects::newEmpty(),
			$this->newRepository()->getSubjectsByPageId( new PageId( 404404404 ) )
		);
	}

	public function testGetSubjectReturnsSubject(): void {
		$this->createPages();

		$this->assertEquals(
			TestSubject::build(
				id: 'sTestMSR1111113',
				label: new SubjectLabel( 'Test subject 3' ),
			),
			$this->newRepository()->getSubject(
				new SubjectId( 'sTestMSR1111113' )
			)
		);
	}

	public function testGetSubjectsReturnsSubjectsFromEveryHostingPage(): void {
		$this->createPages();

		$subjects = $this->newRepository()->getSubjects(
			new SubjectIdList( [
				new SubjectId( 'sTestMSR1111113' ),
				new SubjectId( 'sTestMSR1111115' ),
			] )
		);

		$this->assertEqualsCanonicalizing(
			[
				TestSubject::build( id: 'sTestMSR1111113', label: new SubjectLabel( 'Test subject 3' ) ),
				TestSubject::build( id: 'sTestMSR1111115', label: new SubjectLabel( 'Test subject 5' ) ),
			],
			$subjects->asArray()
		);
	}

	public function testGetSubjectsOmitsUnknownIds(): void {
		$this->createPages();

		$subjects = $this->newRepository()->getSubjects(
			new SubjectIdList( [
				new SubjectId( 'sTestMSR1111111' ),
				new SubjectId( 'sTestMSR1111114' ),
				new SubjectId( 'sTestMSR1111199' ),
			] )
		);

		$this->assertSame( [ 'sTestMSR1111114' ], $subjects->getIdsAsTextArray() );
	}

	public function testGetSubjectsResolvesEveryIdInOneLookup(): void {
		$this->createPages();

		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();

		foreach ( [ 'sTestMSR1111112', 'sTestMSR1111113', 'sTestMSR1111114' ] as $subjectId ) {
			$pageIdentifiersLookup->addIdentifiers(
				new SubjectId( $subjectId ),
				new PageIdentifiers( $this->getPageId( 'SubjectRepoTestOne' ), 'SubjectRepoTestOne', 0 )
			);
		}

		$repository = new MediaWikiSubjectRepository(
			pageIdentifiersLookup: $pageIdentifiersLookup,
			revisionLookup: $this->getServiceContainer()->getRevisionLookup(),
			pageContentSaver: NeoWikiExtension::getInstance()->getPageContentSaver(),
			titleFactory: $this->getServiceContainer()->getTitleFactory(),
			contentHandlerFactory: $this->getServiceContainer()->getContentHandlerFactory(),
		);

		$subjects = $repository->getSubjects(
			new SubjectIdList( [
				new SubjectId( 'sTestMSR1111112' ),
				new SubjectId( 'sTestMSR1111113' ),
				new SubjectId( 'sTestMSR1111114' ),
			] )
		);

		$this->assertCount( 3, $subjects );
		$this->assertSame( 1, $pageIdentifiersLookup->getPageIdsOfSubjectsCallCount );

		// Keeping the batch call and resolving the ids again one at a time would leave the count
		// above at one while costing a graph round trip per id, since the production lookup answers
		// getPageIdOfSubject by running the batch query.
		$this->assertSame( 0, $pageIdentifiersLookup->getPageIdOfSubjectCallCount );
	}

	/**
	 * The slot holds content of another model, which happens when its content model is not
	 * registered and when an import writes something else into it. Reading it as no Subjects would
	 * turn every write into a silent no-op, and savePageSubjects() into an overwrite of whatever the
	 * slot does hold.
	 */
	public function testWriteToAPageWhoseSlotHoldsOtherContentThrowsAndSavesNothing(): void {
		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$pageIdentifiersLookup->addIdentifiers(
			new SubjectId( 'sTestMSR1111121' ),
			new PageIdentifiers( new PageId( 42 ), 'PageWithForeignSlotContent', 0 )
		);

		$pageContentSaver = $this->createMock( PageContentSaver::class );
		$pageContentSaver->expects( $this->never() )->method( 'saveContent' );

		$repository = new MediaWikiSubjectRepository(
			pageIdentifiersLookup: $pageIdentifiersLookup,
			revisionLookup: $this->revisionLookupWithSlotContent(
				new FallbackContent( '{"subjects":{}}', 'unregistered-model' )
			),
			pageContentSaver: $pageContentSaver,
			titleFactory: $this->getServiceContainer()->getTitleFactory(),
			contentHandlerFactory: $this->getServiceContainer()->getContentHandlerFactory(),
		);

		$this->expectException( RuntimeException::class );

		$repository->updateSubject( TestSubject::build( id: 'sTestMSR1111121' ) );
	}

	private function revisionLookupWithSlotContent( Content $content ): RevisionLookup {
		$revision = $this->createStub( RevisionRecord::class );
		$revision->method( 'getContent' )->willReturn( $content );

		$revisionLookup = $this->createStub( RevisionLookup::class );
		$revisionLookup->method( 'getRevisionByPageId' )->willReturn( $revision );

		return $revisionLookup;
	}

	private function getPageId( string $pageName ): PageId {
		return new PageId( Title::newFromText( $pageName )->getId() );
	}

	private const string CREATED_SUBJECT_ID = 'sTestMSR1111aaa';

	private function newPageSubjects(): PageSubjects {
		return new PageSubjects(
			TestSubject::build( id: self::CREATED_SUBJECT_ID, label: null ),
			new SubjectMap()
		);
	}

	public function testCreatePageWithSubjectsCreatesThePageHoldingTheSubject(): void {
		$status = $this->newRepository()->createPageWithSubjects( 'Amsterdam', $this->newPageSubjects(), 'Because' );

		$this->assertSame( PageContentSavingStatus::REVISION_CREATED, $status->status );
		$this->assertEquals( $this->getPageId( 'Amsterdam' ), $status->pageId );
		$this->assertSame(
			self::CREATED_SUBJECT_ID,
			$this->newRepository()->getSubjectsByPageId( $this->getPageId( 'Amsterdam' ) )
				->getMainSubject()?->getId()->text
		);
	}

	/**
	 * The existence check that precedes this reads a replica, so a page created in the meantime -
	 * or simply not replicated yet - would otherwise be edited rather than created, putting the
	 * Subject on a page nobody chose.
	 */
	public function testCreatePageWithSubjectsRefusesAPageThatAlreadyExists(): void {
		$this->editPage( Title::newFromText( 'Amsterdam' ), 'Someone else wrote this' );

		$status = $this->newRepository()->createPageWithSubjects( 'Amsterdam', $this->newPageSubjects(), null );

		$this->assertSame( PageContentSavingStatus::ERROR, $status->status );
		$this->assertNull( $status->pageId );
		$this->assertTrue(
			$this->newRepository()->getSubjectsByPageId( $this->getPageId( 'Amsterdam' ) )->isEmpty()
		);
	}

}
