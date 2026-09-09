<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use LogicException;
use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\NullRevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\RevisionPolicy;
use ProfessionalWiki\NeoWiki\Application\PageRebuilder;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\FixedRevisionPolicy;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyOnRevisionCreatedHandler;
use Wikimedia\Rdbms\IDBAccessObject;
use WikiPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\PageRebuilder
 */
class PageRebuilderTest extends TestCase {

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
			if ( $outcome !== PageRefreshOutcome::Refreshed ) {
				yield $outcome->value => [ $outcome ];
			}
		}
	}

	/**
	 * @covers \ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome::skipReason
	 */
	public function testRefreshedHasNoSkipReason(): void {
		$this->expectException( LogicException::class );

		PageRefreshOutcome::Refreshed->skipReason();
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

	public function testPassesTheCurrentRevisionToTheHandler(): void {
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

	public function testRebuildProjectsTheRevisionThePolicyPublishes(): void {
		$latest = $this->newRevision();
		$published = $this->newRevision();

		$this->newRebuilder( $latest, FixedRevisionPolicy::publishing( $published ) )
			->rebuild( Title::makeTitle( NS_MAIN, 'Reprojected page' ) );

		$this->assertSame( [ $published ], $this->handler->calls );
	}

	public function testRebuildWritesNothingWhenThePolicyPublishesNoRevision(): void {
		$outcome = $this->newRebuilder( $this->newRevision(), FixedRevisionPolicy::publishingNothing() )
			->rebuild( Title::makeTitle( NS_MAIN, 'Page with nothing published' ) );

		$this->assertSame( PageRefreshOutcome::SkippedUnpublishableRevision, $outcome );
		$this->assertSame( [], $this->handler->calls );
	}

	/**
	 * An import or undelete writes a revision rather than reprojecting a page, so it takes the same
	 * path a save does: the handler is handed what was written and decides whether to publish it.
	 */
	public function testRebuildFromPrimaryDoesNotSubstitute(): void {
		$written = $this->newRevision();

		$this->newRebuilder( $written, FixedRevisionPolicy::publishing( $this->newRevision() ) )
			->rebuildFromPrimary( Title::makeTitle( NS_MAIN, 'Imported page' ) );

		$this->assertSame( [ $written ], $this->handler->calls );
	}

	private function newRebuilder( ?RevisionRecord $revision, ?RevisionPolicy $policy = null ): PageRebuilder {
		$page = $this->createStub( WikiPage::class );
		$page->method( 'getRevisionRecord' )->willReturn( $revision );
		$page->method( 'loadPageData' )->willReturnCallback(
			function ( int $from ): void {
				$this->pageDataReads[] = $from;
			}
		);

		$factory = $this->createStub( WikiPageFactory::class );
		$factory->method( 'newFromTitle' )->willReturn( $page );

		return new PageRebuilder( $this->handler, $factory, $policy ?? new NullRevisionPolicy() );
	}

	private function newRevision(): RevisionRecord {
		return $this->createStub( RevisionRecord::class );
	}

}
