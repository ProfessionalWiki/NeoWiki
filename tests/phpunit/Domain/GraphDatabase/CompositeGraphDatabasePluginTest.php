<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\GraphDatabase;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\CompositeGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use Psr\Log\Test\TestLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\CompositeGraphDatabasePlugin
 */
class CompositeGraphDatabasePluginTest extends TestCase {

	private TestLogger $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->logger = new TestLogger();
	}

	private function newComposite( GraphDatabasePlugin ...$plugins ): CompositeGraphDatabasePlugin {
		return new CompositeGraphDatabasePlugin( $this->logger, ...$plugins );
	}

	public function testEmptyCompositeDoesNotThrow(): void {
		$composite = $this->newComposite();

		$composite->savePage( TestPage::build() );
		$composite->deletePage( new PageId( 1 ) );

		$this->addToAssertionCount( 1 );
	}

	public function testSavePageDispatchesToAllPlugins(): void {
		$spy1 = new SpyGraphDatabasePlugin();
		$spy2 = new SpyGraphDatabasePlugin();
		$composite = $this->newComposite( $spy1, $spy2 );

		$page = TestPage::build( id: 42 );
		$composite->savePage( $page );

		$this->assertSame( [ $page ], $spy1->savedPages );
		$this->assertSame( [ $page ], $spy2->savedPages );
	}

	public function testDeletePageDispatchesToAllPlugins(): void {
		$spy1 = new SpyGraphDatabasePlugin();
		$spy2 = new SpyGraphDatabasePlugin();
		$composite = $this->newComposite( $spy1, $spy2 );

		$pageId = new PageId( 42 );
		$composite->deletePage( $pageId );

		$this->assertSame( [ $pageId ], $spy1->deletedPageIds );
		$this->assertSame( [ $pageId ], $spy2->deletedPageIds );
	}

	public function testSinglePluginReceivesAllCalls(): void {
		$spy = new SpyGraphDatabasePlugin();
		$composite = $this->newComposite( $spy );

		$page1 = TestPage::build( id: 1 );
		$page2 = TestPage::build( id: 2 );
		$composite->savePage( $page1 );
		$composite->savePage( $page2 );
		$composite->deletePage( new PageId( 1 ) );

		$this->assertSame( [ $page1, $page2 ], $spy->savedPages );
		$this->assertCount( 1, $spy->deletedPageIds );
	}

	public function testSavePageReachesPluginsAfterAThrowingOne(): void {
		$spyBefore = new SpyGraphDatabasePlugin();
		$spyAfter = new SpyGraphDatabasePlugin();
		$composite = $this->newComposite(
			$spyBefore,
			new ThrowingGraphDatabasePlugin(),
			$spyAfter
		);

		$page = TestPage::build( id: 42 );
		$composite->savePage( $page );

		$this->assertSame( [ $page ], $spyBefore->savedPages );
		$this->assertSame( [ $page ], $spyAfter->savedPages, 'a plugin failure must not starve later plugins' );
	}

	public function testDeletePageReachesPluginsAfterAThrowingOne(): void {
		$spyBefore = new SpyGraphDatabasePlugin();
		$spyAfter = new SpyGraphDatabasePlugin();
		$composite = $this->newComposite(
			$spyBefore,
			new ThrowingGraphDatabasePlugin(),
			$spyAfter
		);

		$pageId = new PageId( 42 );
		$composite->deletePage( $pageId );

		$this->assertSame( [ $pageId ], $spyBefore->deletedPageIds );
		$this->assertSame( [ $pageId ], $spyAfter->deletedPageIds, 'a plugin failure must not starve later plugins' );
	}

	public function testFailingSavePageIsLoggedAsErrorWithReconciliationHint(): void {
		$composite = $this->newComposite( new ThrowingGraphDatabasePlugin() );

		$composite->savePage( TestPage::build( id: 42 ) );

		$this->assertTrue( $this->logger->hasErrorThatContains( 'RebuildGraphDatabases' ) );
		$this->assertTrue( $this->logger->hasErrorThatContains( '42' ) );
	}

	public function testFailingDeletePageIsLoggedAsErrorWithReconciliationHint(): void {
		$composite = $this->newComposite( new ThrowingGraphDatabasePlugin() );

		$composite->deletePage( new PageId( 42 ) );

		$this->assertTrue( $this->logger->hasErrorThatContains( 'RebuildGraphDatabases' ) );
		$this->assertTrue( $this->logger->hasErrorThatContains( '42' ) );
	}

	public function testSucceedingProjectionIsNotLogged(): void {
		$composite = $this->newComposite( new SpyGraphDatabasePlugin() );

		$composite->savePage( TestPage::build( id: 42 ) );

		$this->assertFalse( $this->logger->hasErrorRecords() );
	}

}
