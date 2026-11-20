<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageRebuilder;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpySubjectIndexer;
use Wikimedia\Rdbms\IDBAccessObject;
use WikiPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\PageRebuilder
 */
class PageRebuilderTest extends TestCase {

	private const int PAGE_ID = 7;

	private SpySubjectIndexer $indexer;

	/**
	 * @var PageId[]
	 */
	private array $queuedProjections;

	/**
	 * @var int[]
	 */
	private array $pageDataReads;

	protected function setUp(): void {
		$this->indexer = new SpySubjectIndexer();
		$this->queuedProjections = [];
		$this->pageDataReads = [];
	}

	public function testIndexesTheCurrentRevision(): void {
		$revision = $this->createStub( RevisionRecord::class );

		$this->newRebuilder( $revision )->rebuild( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertSame( [ $revision ], $this->indexer->indexedRevisions );
	}

	public function testQueuesTheProjectionOfThePage(): void {
		$this->newRebuilder( $this->createStub( RevisionRecord::class ) )
			->rebuild( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertEquals( [ new PageId( self::PAGE_ID ) ], $this->queuedProjections );
	}

	public function testReadsPageStateFromPrimary(): void {
		$this->newRebuilder( $this->createStub( RevisionRecord::class ) )
			->rebuild( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertSame(
			[ IDBAccessObject::READ_LATEST ],
			$this->pageDataReads,
			'a rebuild right after a write must not be served the revision the write replaced'
		);
	}

	public function testLeavesAPageWithoutACurrentRevisionAlone(): void {
		$found = $this->newRebuilder( null )->rebuild( Title::makeTitle( NS_MAIN, 'Missing' ) );

		$this->assertFalse( $found );
		$this->assertSame( [], $this->indexer->indexedRevisions );
		$this->assertSame( [], $this->queuedProjections );
	}

	private function newRebuilder( ?RevisionRecord $revision ): PageRebuilder {
		$page = $this->createStub( WikiPage::class );
		$page->method( 'getRevisionRecord' )->willReturn( $revision );
		$page->method( 'getId' )->willReturn( $revision === null ? 0 : self::PAGE_ID );
		$page->method( 'loadPageData' )->willReturnCallback(
			function ( int $from ): void {
				$this->pageDataReads[] = $from;
			}
		);

		$factory = $this->createStub( WikiPageFactory::class );
		$factory->method( 'newFromTitle' )->willReturn( $page );

		return new PageRebuilder(
			$this->indexer,
			$factory,
			function ( PageId $pageId ): void {
				$this->queuedProjections[] = $pageId;
			}
		);
	}

}
