<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionLookup;
use MediaWiki\Revision\RevisionRecord;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\PublishedSubjectLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\PublishedSubjectLookup
 * @group Database
 */
class PublishedSubjectLookupTest extends NeoWikiIntegrationTestCase {

	private function newLookup(
		RevisionPolicy $revisionPolicy,
		InMemoryPageIdentifiersLookup $pageIdentifiersLookup,
		?RevisionLookup $revisionLookup = null,
	): PublishedSubjectLookup {
		return new PublishedSubjectLookup(
			pageIdentifiersLookup: $pageIdentifiersLookup,
			revisionLookup: $revisionLookup ?? MediaWikiServices::getInstance()->getRevisionLookup(),
			revisionPolicy: $revisionPolicy,
		);
	}

	public function testReadsEachHostingPageAtTheRevisionItPublishes(): void {
		$firstPublished = TestSubject::build( id: 'sPubTest1111111', label: new SubjectLabel( 'First published' ) );
		$secondPublished = TestSubject::build( id: 'sPubTest1111112', label: new SubjectLabel( 'Second published' ) );
		$onOtherPage = TestSubject::build( id: 'sPubTest1111113', label: new SubjectLabel( 'On the other page' ) );

		$publishedRevision = $this->createPageWithSubjects(
			'PublishedTestApproved',
			mainSubject: $firstPublished,
			otherSubjects: new SubjectMap( $secondPublished ),
		);

		$this->createPageWithSubjects(
			'PublishedTestApproved',
			mainSubject: TestSubject::build( id: 'sPubTest1111111', label: new SubjectLabel( 'First draft' ) ),
			otherSubjects: new SubjectMap( TestSubject::build( id: 'sPubTest1111112', label: new SubjectLabel( 'Second draft' ) ) ),
		);

		$otherRevision = $this->createPageWithSubjects( 'PublishedTestOther', mainSubject: $onOtherPage );

		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$this->registerHostingPage( $pageIdentifiersLookup, $firstPublished->id, $publishedRevision, 'PublishedTestApproved' );
		$this->registerHostingPage( $pageIdentifiersLookup, $secondPublished->id, $publishedRevision, 'PublishedTestApproved' );
		$this->registerHostingPage( $pageIdentifiersLookup, $onOtherPage->id, $otherRevision, 'PublishedTestOther' );

		$subjects = $this->newLookup( FixedRevisionPolicy::publishing( $publishedRevision ), $pageIdentifiersLookup )
			->getSubjects( new SubjectIdList( [ $firstPublished->id, $secondPublished->id, $onOtherPage->id ] ) );

		$this->assertEqualsCanonicalizing(
			[ $firstPublished, $secondPublished, $onOtherPage ],
			$subjects->asArray()
		);
	}

	public function testYieldsNoSubjectsForAPageThatPublishesNothing(): void {
		$subject = TestSubject::build( id: 'sPubTest1111114' );
		$revision = $this->createPageWithSubjects( 'PublishedTestNothing', mainSubject: $subject );

		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$this->registerHostingPage( $pageIdentifiersLookup, $subject->id, $revision, 'PublishedTestNothing' );

		$subjects = $this->newLookup( FixedRevisionPolicy::publishingNothing(), $pageIdentifiersLookup )
			->getSubjects( new SubjectIdList( [ $subject->id ] ) );

		$this->assertTrue( $subjects->isEmpty() );
	}

	public function testEmptyIdListReadsNoRevision(): void {
		$revisionLookup = $this->createMock( RevisionLookup::class );
		$revisionLookup->expects( $this->never() )->method( 'getRevisionByPageId' );

		$subjects = $this
			->newLookup( FixedRevisionPolicy::publishingNothing(), new InMemoryPageIdentifiersLookup(), $revisionLookup )
			->getSubjects( new SubjectIdList( [] ) );

		$this->assertTrue( $subjects->isEmpty() );
	}

	public function testReturnsNullForASubjectHostedByNoPage(): void {
		$lookup = $this->newLookup( FixedRevisionPolicy::publishingNothing(), new InMemoryPageIdentifiersLookup() );

		$this->assertNull( $lookup->getSubject( new SubjectId( 'sPubTest1111115' ) ) );
	}

	private function registerHostingPage(
		InMemoryPageIdentifiersLookup $pageIdentifiersLookup,
		SubjectId $subjectId,
		RevisionRecord $revision,
		string $pageName
	): void {
		$pageIdentifiersLookup->addIdentifiers(
			$subjectId,
			new PageIdentifiers( new PageId( $revision->getPage()->getId() ), $pageName, 0 )
		);
	}

}
