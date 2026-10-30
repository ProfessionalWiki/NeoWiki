<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Parser\ParserOutput;
use MediaWiki\Revision\RenderedRevision;
use MediaWiki\Revision\RevisionRecord;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\CurrentPageProjector;
use ProfessionalWiki\NeoWiki\Application\PageProjectionLock;
use ProfessionalWiki\NeoWiki\Application\PageProjectionLockUnavailableException;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphStoreProjector;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyPageRenderings;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\StubPageProjectionLock;
use Wikimedia\Rdbms\IDBAccessObject;
use WikiPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\CurrentPageProjector
 */
class CurrentPageProjectorTest extends TestCase {

	private const int PAGE_ID = 7;

	private SpyGraphStoreProjector $graphStoreProjector;

	private SpyPageRenderings $renderings;

	/**
	 * @var int[]
	 */
	private array $pageDataReads;

	protected function setUp(): void {
		$this->graphStoreProjector = new SpyGraphStoreProjector();
		$this->renderings = new SpyPageRenderings();
		$this->pageDataReads = [];
	}

	public function testProjectsTheCurrentRevision(): void {
		$revision = $this->createStub( RevisionRecord::class );

		$this->newProjector( $revision )->project( new PageId( self::PAGE_ID ), null );

		$this->assertSame( [ $revision ], $this->graphStoreProjector->projections );
	}

	public function testHandsMediaWikisRenderingToTheProjection(): void {
		$rendering = $this->newRendering( new ParserOutput() );

		$this->newProjector( $this->createStub( RevisionRecord::class ) )->project( new PageId( self::PAGE_ID ), $rendering );

		$this->assertSame( [ $rendering ], $this->graphStoreProjector->renderings );
	}

	public function testReadsPageStateFromPrimary(): void {
		$this->newProjector( $this->createStub( RevisionRecord::class ) )->project( new PageId( self::PAGE_ID ), null );

		$this->assertSame(
			[ IDBAccessObject::READ_LATEST ],
			$this->pageDataReads,
			'a projection right after an edit must not be served the revision the edit replaced'
		);
	}

	public function testWithdrawsAPageThatNoLongerExists(): void {
		$this->newProjector( null )->project( new PageId( self::PAGE_ID ), null );

		$this->assertEquals( [ new PageId( self::PAGE_ID ) ], $this->graphStoreProjector->withdrawals );
	}

	public function testExpiresTheRenderingsMediaWikiMadeBeforeTheProjection(): void {
		$parserOutput = new ParserOutput();

		$this->newProjector( $this->createStub( RevisionRecord::class ) )
			->project( new PageId( self::PAGE_ID ), $this->newRendering( $parserOutput ) );

		$this->assertEquals( [ new PageId( self::PAGE_ID ) ], $this->renderings->expiredPageIds );
		$this->assertSame( [ $parserOutput ], $this->renderings->renderingsSeen );
	}

	public function testExpiresTheRenderingsWithoutKnowingWhatMediaWikiRenderedBeforeTheProjection(): void {
		$this->newProjector( $this->createStub( RevisionRecord::class ) )->project( new PageId( self::PAGE_ID ), null );

		$this->assertEquals( [ new PageId( self::PAGE_ID ) ], $this->renderings->expiredPageIds );
		$this->assertSame( [ null ], $this->renderings->renderingsSeen );
	}

	public function testLeavesThePageAloneWhileAnotherProjectionHoldsIt(): void {
		$projector = $this->newProjectorWithLock(
			$this->createStub( RevisionRecord::class ),
			StubPageProjectionLock::heldElsewhere()
		);

		try {
			$projector->project( new PageId( self::PAGE_ID ), null );
			$this->fail( 'the projection was supposed to give up on the page' );
		} catch ( PageProjectionLockUnavailableException ) {
		}

		$this->assertSame( [], $this->graphStoreProjector->projections );
		$this->assertSame( [], $this->graphStoreProjector->withdrawals );
	}

	private function newProjector( ?RevisionRecord $revision ): CurrentPageProjector {
		return $this->newProjectorWithLock( $revision, StubPageProjectionLock::available() );
	}

	private function newProjectorWithLock( ?RevisionRecord $revision, PageProjectionLock $lock ): CurrentPageProjector {
		$page = $this->createStub( WikiPage::class );
		$page->method( 'getRevisionRecord' )->willReturn( $revision );

		$factory = $this->createStub( WikiPageFactory::class );
		$factory->method( 'newFromID' )->willReturnCallback(
			function ( int $id, int $from ) use ( $page, $revision ): ?WikiPage {
				$this->pageDataReads[] = $from;
				return $revision === null ? null : $page;
			}
		);

		return new CurrentPageProjector( $factory, $this->graphStoreProjector, $lock, $this->renderings );
	}

	private function newRendering( ParserOutput $parserOutput ): RenderedRevision {
		$rendering = $this->createStub( RenderedRevision::class );
		$rendering->method( 'getRevisionParserOutput' )->willReturn( $parserOutput );

		return $rendering;
	}

}
