<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\Title\Title;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Application\PageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectIdList;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePageIdentifiersLookup;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIndex;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabasePageIdentifiersLookup
 * @group Database
 */
class DatabasePageIdentifiersLookupTest extends MediaWikiIntegrationTestCase {

	private const SUBJECT_ID = 's11111111111111';
	private const OTHER_SUBJECT_ID = 's22222222222222';
	private const UNINDEXED_SUBJECT_ID = 's33333333333333';
	private const MISSING_PAGE_ID = 999999;

	public function testSubjectResolvesToThePageHoldingIt(): void {
		$pageId = $this->createPage( 'Help:Indexed page' );
		$this->index( self::SUBJECT_ID, $pageId );

		$identifiers = $this->newLookup()->getPageIdOfSubject( new SubjectId( self::SUBJECT_ID ) );

		$this->assertNotNull( $identifiers );
		$this->assertSame( $pageId, $identifiers->getId()->id );
		$this->assertSame( 'Help:Indexed page', $identifiers->getTitle() );
		$this->assertSame( NS_HELP, $identifiers->getNamespaceId() );
	}

	public function testUnindexedSubjectResolvesToNothing(): void {
		$this->index( self::SUBJECT_ID, $this->createPage( 'Indexed page' ) );

		$this->assertNull(
			$this->newLookup()->getPageIdOfSubject( new SubjectId( self::UNINDEXED_SUBJECT_ID ) )
		);
	}

	/**
	 * A row naming a page the wiki does not have has nothing to join, which is what makes the rows a
	 * deletion the index was not told about leaves behind inert rather than misleading.
	 */
	public function testSubjectOfAMissingPageResolvesToNothing(): void {
		$this->index( self::SUBJECT_ID, self::MISSING_PAGE_ID );

		$this->assertNull(
			$this->newLookup()->getPageIdOfSubject( new SubjectId( self::SUBJECT_ID ) )
		);
	}

	/**
	 * Cross-wiki transfer can bring the same Subject id onto a second page (ADR 5), which must not fail
	 * either page's save. The id then resolves to the lowest page id, so every reader gets the same page.
	 */
	public function testDuplicateSubjectResolvesToTheLowestPageId(): void {
		$firstPageId = $this->createPage( 'First holder' );
		$secondPageId = $this->createPage( 'Second holder' );
		$this->index( self::SUBJECT_ID, $secondPageId );
		$this->index( self::SUBJECT_ID, $firstPageId );

		$identifiers = $this->newLookup()->getPageIdOfSubject( new SubjectId( self::SUBJECT_ID ) );

		$this->assertNotNull( $identifiers );
		$this->assertSame( $firstPageId, $identifiers->getId()->id );
	}

	public function testSubjectsAreLookedUpTogether(): void {
		$firstPageId = $this->createPage( 'First page' );
		$secondPageId = $this->createPage( 'Second page' );
		$this->index( self::SUBJECT_ID, $firstPageId );
		$this->index( self::OTHER_SUBJECT_ID, $secondPageId );

		$identifiers = $this->newLookup()->getPageIdsOfSubjects( new SubjectIdList( [
			new SubjectId( self::SUBJECT_ID ),
			new SubjectId( self::OTHER_SUBJECT_ID ),
			new SubjectId( self::UNINDEXED_SUBJECT_ID ),
		] ) );

		$this->assertSame(
			[ self::SUBJECT_ID => $firstPageId, self::OTHER_SUBJECT_ID => $secondPageId ],
			array_map( static fn ( $item ): int => $item->getId()->id, $identifiers )
		);
	}

	public function testNoSubjectsAreLookedUpAtAll(): void {
		$this->assertSame( [], $this->newLookup()->getPageIdsOfSubjects( new SubjectIdList( [] ) ) );
	}

	private function newLookup(): PageIdentifiersLookup {
		return new DatabasePageIdentifiersLookup(
			$this->getDb(),
			$this->getServiceContainer()->getTitleFormatter()
		);
	}

	private function createPage( string $title ): int {
		return $this->getExistingTestPage( Title::newFromText( $title ) )->getId();
	}

	private function index( string $subjectId, int $pageId ): void {
		$this->getDb()->newInsertQueryBuilder()
			->insertInto( DatabaseSubjectPageIndex::TABLE )
			->row( [ 'nwsp_subject_id' => $subjectId, 'nwsp_page_id' => $pageId ] )
			->caller( __METHOD__ )
			->execute();
	}

}
