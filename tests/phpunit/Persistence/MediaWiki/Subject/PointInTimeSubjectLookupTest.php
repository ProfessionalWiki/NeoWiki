<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Domain\Page\PageIdentifiers;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\PointInTimeSubjectLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemoryPageIdentifiersLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\PointInTimeSubjectLookup
 * @group Database
 */
class PointInTimeSubjectLookupTest extends NeoWikiIntegrationTestCase {

	private function newLookup(
		RevisionRecord $primaryRevision,
		InMemoryPageIdentifiersLookup $pageIdentifiersLookup = new InMemoryPageIdentifiersLookup(),
	): PointInTimeSubjectLookup {
		$services = MediaWikiServices::getInstance();
		return new PointInTimeSubjectLookup(
			revisionLookup: $services->getRevisionLookup(),
			pageIdentifiersLookup: $pageIdentifiersLookup,
			connectionProvider: $services->getDBLoadBalancerFactory(),
			primaryRevision: $primaryRevision,
		);
	}

	public function testReturnsNullForUnknownSubject(): void {
		$revision = $this->createPageWithSubjects(
			'PitTestEmpty',
			mainSubject: TestSubject::build( id: 'sPitTest1111111' ),
		);

		$lookup = $this->newLookup( $revision );

		$this->assertNull(
			$lookup->getSubject( new SubjectId( 'sPitTest9999999' ) )
		);
	}

	public function testReturnsMainSubjectFromPrimaryRevision(): void {
		$subject = TestSubject::build(
			id: 'sPitTest1111112',
			label: new SubjectLabel( 'Main subject' ),
		);

		$revision = $this->createPageWithSubjects(
			'PitTestMain',
			mainSubject: $subject,
		);

		$lookup = $this->newLookup( $revision );

		$this->assertEquals(
			$subject,
			$lookup->getSubject( new SubjectId( 'sPitTest1111112' ) )
		);
	}

	public function testReturnsChildSubjectFromPrimaryRevision(): void {
		$child = TestSubject::build(
			id: 'sPitTest1111113',
			label: new SubjectLabel( 'Child subject' ),
		);

		$revision = $this->createPageWithSubjects(
			'PitTestChild',
			mainSubject: TestSubject::build( id: 'sPitTest1111114' ),
			childSubjects: new SubjectMap( $child ),
		);

		$lookup = $this->newLookup( $revision );

		$this->assertEquals(
			$child,
			$lookup->getSubject( new SubjectId( 'sPitTest1111113' ) )
		);
	}

	public function testReturnsNullWhenPrimaryRevisionHasNoNeoSlot(): void {
		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
			Title::newFromText( 'PitTestNoSlot' )
		);
		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new TextContent( 'plain page' ) );
		$revision = $updater->saveRevision(
			CommentStoreComment::newUnsavedComment( 'test' )
		);

		$lookup = $this->newLookup( $revision );

		$this->assertNull(
			$lookup->getSubject( new SubjectId( 'sPitTest1111115' ) )
		);
	}

	public function testReturnsHistoricalVersionFromOlderRevision(): void {
		$originalSubject = TestSubject::build(
			id: 'sPitTest1111116',
			label: new SubjectLabel( 'Original' ),
		);

		$oldRevision = $this->createPageWithSubjects(
			'PitTestHistory',
			mainSubject: $originalSubject,
		);

		$updatedSubject = TestSubject::build(
			id: 'sPitTest1111116',
			label: new SubjectLabel( 'Updated' ),
		);

		$this->createPageWithSubjects(
			'PitTestHistory',
			mainSubject: $updatedSubject,
		);

		$lookup = $this->newLookup( $oldRevision );

		$result = $lookup->getSubject( new SubjectId( 'sPitTest1111116' ) );

		$this->assertEquals( $originalSubject, $result );
	}

	public function testReturnsSubjectFromDifferentPageAtPointInTime(): void {
		$crossPageSubject = TestSubject::build(
			id: 'sPitTest1111117',
			label: new SubjectLabel( 'Cross-page subject' ),
		);

		$crossPageRevision = $this->createPageWithSubjects(
			'PitTestCrossPage',
			mainSubject: $crossPageSubject,
		);

		$primaryRevision = $this->createPageWithSubjects(
			'PitTestPrimary',
			mainSubject: TestSubject::build( id: 'sPitTest1111118' ),
		);

		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$pageIdentifiersLookup->addIdentifiers(
			new SubjectId( 'sPitTest1111117' ),
			new PageIdentifiers(
				new PageId( $crossPageRevision->getPage()->getId() ),
				'PitTestCrossPage',
			),
		);

		$lookup = $this->newLookup( $primaryRevision, $pageIdentifiersLookup );

		$this->assertEquals(
			$crossPageSubject,
			$lookup->getSubject( new SubjectId( 'sPitTest1111117' ) )
		);
	}

	public function testReturnsNullWhenCrossPageHasNoRevisionAtPointInTime(): void {
		$primaryRevision = $this->createPageWithSubjects(
			'PitTestNoCross',
			mainSubject: TestSubject::build( id: 'sPitTest1111119' ),
		);

		$pageIdentifiersLookup = new InMemoryPageIdentifiersLookup();
		$pageIdentifiersLookup->addIdentifiers(
			new SubjectId( 'sPitTest111111a' ),
			new PageIdentifiers(
				new PageId( 404404404 ),
				'NonexistentPage',
			),
		);

		$lookup = $this->newLookup( $primaryRevision, $pageIdentifiersLookup );

		$this->assertNull(
			$lookup->getSubject( new SubjectId( 'sPitTest111111a' ) )
		);
	}

}
