<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use LogicException;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\PageRebuilder;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyOnRevisionCreatedHandler;
use Wikimedia\Rdbms\IDBAccessObject;
use WikiPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\PageRebuilder
 */
class PageRebuilderTest extends TestCase {

	/**
	 * The outcomes of a page that was written rather than skipped.
	 */
	private const array WRITE_OUTCOMES = [ PageRefreshOutcome::Refreshed, PageRefreshOutcome::Unpublished ];

	private SpyOnRevisionCreatedHandler $handler;

	/**
	 * @var int[]
	 */
	private array $pageDataReads;

	protected function setUp(): void {
		$this->handler = new SpyOnRevisionCreatedHandler();
		$this->pageDataReads = [];
	}

	/**
	 * @dataProvider skipOutcomeProvider
	 * @covers \ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome::skipReason
	 */
	public function testEverySkipOutcomeExplainsItself( PageRefreshOutcome $outcome ): void {
		$this->assertNotSame( '', $outcome->skipReason() );
	}

	/**
	 * @return iterable<array{PageRefreshOutcome}>
	 */
	public static function skipOutcomeProvider(): iterable {
		foreach ( PageRefreshOutcome::cases() as $outcome ) {
			if ( !in_array( $outcome, self::WRITE_OUTCOMES, true ) ) {
				yield $outcome->value => [ $outcome ];
			}
		}
	}

	/**
	 * @dataProvider writeOutcomeProvider
	 * @covers \ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome::skipReason
	 */
	public function testAnOutcomeThatWroteHasNoSkipReason( PageRefreshOutcome $outcome ): void {
		$this->expectException( LogicException::class );

		$outcome->skipReason();
	}

	/**
	 * @return iterable<array{PageRefreshOutcome}>
	 */
	public static function writeOutcomeProvider(): iterable {
		foreach ( self::WRITE_OUTCOMES as $outcome ) {
			yield $outcome->value => [ $outcome ];
		}
	}

	public function testReturnsRefreshedWhenHandlerWritesPage(): void {
		$this->handler->outcome = PageRefreshOutcome::Refreshed;

		$outcome = $this->newRebuilder( $this->newRevision() )
			->rebuild( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertSame( PageRefreshOutcome::Refreshed, $outcome );
	}

	public function testReturnsTheOutcomeOfTheHandlerForAnExistingPage(): void {
		$this->handler->outcome = PageRefreshOutcome::SkippedUnreadableSubjects;

		$outcome = $this->newRebuilder( $this->newRevision() )
			->rebuild( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertSame( PageRefreshOutcome::SkippedUnreadableSubjects, $outcome );
	}

	public function testReturnsSkippedMissingRevisionWhenNoCurrentRevision(): void {
		$outcome = $this->newRebuilder( null )->rebuild( Title::makeTitle( NS_MAIN, 'Missing' ) );

		$this->assertSame( PageRefreshOutcome::SkippedMissingRevision, $outcome );
		$this->assertSame( [], $this->handler->calls );
	}

	public function testRebuildPassesTheCurrentRevisionToTheHandler(): void {
		$revision = $this->newRevision();

		$this->newRebuilder( $revision )->rebuild( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertSame( [ $revision ], $this->handler->calls );
	}

	public function testRebuildReadsPageStateFromReplica(): void {
		$this->newRebuilder( $this->newRevision() )
			->rebuild( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertSame( [ IDBAccessObject::READ_NORMAL ], $this->pageDataReads );
	}

	public function testRebuildFromPrimaryReadsPageStateFromPrimary(): void {
		$this->newRebuilder( $this->newRevision() )
			->rebuildFromPrimary( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertSame(
			[ IDBAccessObject::READ_LATEST ],
			$this->pageDataReads,
			'a rebuild right after a write must not be served the revision the write replaced'
		);
	}

	public function testRebuildFromPrimaryPassesTheCurrentRevisionToTheHandler(): void {
		$revision = $this->newRevision();

		$this->newRebuilder( $revision )->rebuildFromPrimary( Title::makeTitle( NS_MAIN, 'Imported page' ) );

		$this->assertSame( [ $revision ], $this->handler->calls );
	}

	private function newRebuilder( ?RevisionRecord $revision ): PageRebuilder {
		$page = $this->createStub( WikiPage::class );
		$page->method( 'getRevisionRecord' )->willReturn( $revision );
		$page->method( 'loadPageData' )->willReturnCallback(
			function ( int $from ): void {
				$this->pageDataReads[] = $from;
			}
		);

		$factory = $this->createStub( WikiPageFactory::class );
		$factory->method( 'newFromTitle' )->willReturn( $page );

		return new PageRebuilder( $this->handler, $factory );
	}

	private function newRevision(): RevisionRecord {
		return $this->createStub( RevisionRecord::class );
	}

}
