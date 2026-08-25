<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki\Subject;

use MediaWiki\CommentStore\CommentStoreComment;
use MediaWiki\Content\TextContent;
use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\EntryPoints\Content\SubjectContent;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\MediaWikiSubjectRepository;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * A local slot holds local Subjects only (ADR 23). Imported or hand-edited content can still carry an
 * entry keyed by a qualified id, and every consumer of the slot has to agree on what happens to it:
 * the subject-to-page index reads those keys as bare local ids (ADR 32) and drops such an entry, so
 * the projectors must drop it too rather than write a node nothing can find a page for.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\Subject\SubjectContentDataDeserializer
 * @group Database
 */
class NonLocalSlotEntryProjectionTest extends NeoWikiIntegrationTestCase {

	private const string PAGE_NAME = 'NonLocalSlotEntryTest';
	private const string LOCAL_ID = 'sTestNLS1111111';
	private const string FOREIGN_ID = 'catalog:widget-7';

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( 'NonLocalSlotEntrySchema' );
		$this->createPageWithSlotJson();
	}

	public function testTheLocalSubjectIsProjected(): void {
		$this->assertSame( [ self::LOCAL_ID ], $this->projectedSubjectIds() );
	}

	public function testTheEntryFromAnotherSourceIsNotProjected(): void {
		$this->assertNotContains( self::FOREIGN_ID, $this->projectedSubjectIds() );
	}

	public function testThePageServesOnlyItsLocalSubject(): void {
		$pageId = Title::newFromText( self::PAGE_NAME )->getId();

		$subjects = NeoWikiExtension::getInstance()
			->getSubjectRepository()
			->getSubjectsByPageId( new PageId( $pageId ) );

		$this->assertSame(
			[ self::LOCAL_ID ],
			array_map(
				static fn ( $subject ): string => $subject->getId()->text,
				$subjects->getAllSubjects()->asArray()
			)
		);
	}

	/**
	 * @return string[]
	 */
	private function projectedSubjectIds(): array {
		$ids = [];

		foreach ( $this->readGraph( 'MATCH (subject:Subject) RETURN subject.id AS id' ) as $record ) {
			$ids[] = $record->get( 'id' );
		}

		return $ids;
	}

	private function createPageWithSlotJson(): void {
		$json = json_encode( [
			'mainSubject' => self::LOCAL_ID,
			'subjects' => [
				self::FOREIGN_ID => [ 'label' => 'Widget 7', 'schema' => 'NonLocalSlotEntrySchema' ],
				self::LOCAL_ID => [ 'label' => 'Local subject', 'schema' => 'NonLocalSlotEntrySchema' ],
			],
		] );

		$wikiPage = MediaWikiServices::getInstance()->getWikiPageFactory()
			->newFromTitle( Title::newFromText( self::PAGE_NAME ) );

		$updater = $wikiPage->newPageUpdater( $this->getTestSysop()->getUser() );
		$updater->setContent( 'main', new TextContent( '' ) );
		$updater->setContent( MediaWikiSubjectRepository::SLOT_NAME, new SubjectContent( $json ) );
		$updater->saveRevision( CommentStoreComment::newUnsavedComment( 'Slot with a qualified key' ) );
	}

}
