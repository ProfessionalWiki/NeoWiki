<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use MediaWiki\Page\WikiPageFactory;
use MediaWiki\Revision\RevisionRecord;
use MediaWiki\Title\Title;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;
use ProfessionalWiki\NeoWiki\Application\GraphStorePageRebuilder;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphStoreProjector;
use Wikimedia\Rdbms\IDBAccessObject;
use WikiPage;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\GraphStorePageRebuilder
 */
class GraphStorePageRebuilderTest extends TestCase {

	private SpyGraphStoreProjector $graphStoreProjector;

	/**
	 * @var int[]
	 */
	private array $pageDataReads;

	protected function setUp(): void {
		$this->graphStoreProjector = new SpyGraphStoreProjector();
		$this->pageDataReads = [];
	}

	public function testProjectsTheCurrentRevision(): void {
		$revision = $this->createStub( RevisionRecord::class );

		$this->newRebuilder( $revision )->rebuildFromReplica( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertSame( [ $revision ], $this->graphStoreProjector->projections );
	}

	public function testLeavesThePrimaryDatabaseAlone(): void {
		$this->newRebuilder( $this->createStub( RevisionRecord::class ) )
			->rebuildFromReplica( Title::makeTitle( NS_MAIN, 'AnyPage' ) );

		$this->assertNotContains( IDBAccessObject::READ_LATEST, $this->pageDataReads );
	}

	public function testSkipsAPageWithoutACurrentRevision(): void {
		$outcome = $this->newRebuilder( null )->rebuildFromReplica( Title::makeTitle( NS_MAIN, 'Missing' ) );

		$this->assertSame( PageRefreshOutcome::SkippedMissingRevision, $outcome );
		$this->assertSame( [], $this->graphStoreProjector->projections );
	}

	private function newRebuilder( ?RevisionRecord $revision ): GraphStorePageRebuilder {
		$page = $this->createStub( WikiPage::class );
		$page->method( 'getRevisionRecord' )->willReturn( $revision );
		$page->method( 'loadPageData' )->willReturnCallback(
			function ( int $from ): void {
				$this->pageDataReads[] = $from;
			}
		);

		$factory = $this->createStub( WikiPageFactory::class );
		$factory->method( 'newFromTitle' )->willReturn( $page );

		return new GraphStorePageRebuilder( $factory, $this->graphStoreProjector );
	}

}
