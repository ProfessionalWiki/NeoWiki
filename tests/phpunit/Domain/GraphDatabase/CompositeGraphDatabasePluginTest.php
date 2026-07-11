<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\GraphDatabase;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\CompositeGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\FailureIsolatingGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use Psr\Log\NullLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\CompositeGraphDatabasePlugin
 */
class CompositeGraphDatabasePluginTest extends TestCase {

	public function testEmptyCompositeDoesNotThrow(): void {
		$composite = new CompositeGraphDatabasePlugin();

		$composite->savePage( TestPage::build() );
		$composite->deletePage( new PageId( 1 ) );

		$this->addToAssertionCount( 1 );
	}

	public function testSavePageDispatchesToAllPlugins(): void {
		$spy1 = new SpyGraphDatabasePlugin();
		$spy2 = new SpyGraphDatabasePlugin();
		$composite = new CompositeGraphDatabasePlugin( $spy1, $spy2 );

		$page = TestPage::build( id: 42 );
		$composite->savePage( $page );

		$this->assertSame( [ $page ], $spy1->savedPages );
		$this->assertSame( [ $page ], $spy2->savedPages );
	}

	public function testDeletePageDispatchesToAllPlugins(): void {
		$spy1 = new SpyGraphDatabasePlugin();
		$spy2 = new SpyGraphDatabasePlugin();
		$composite = new CompositeGraphDatabasePlugin( $spy1, $spy2 );

		$pageId = new PageId( 42 );
		$composite->deletePage( $pageId );

		$this->assertSame( [ $pageId ], $spy1->deletedPageIds );
		$this->assertSame( [ $pageId ], $spy2->deletedPageIds );
	}

	public function testSinglePluginReceivesAllCalls(): void {
		$spy = new SpyGraphDatabasePlugin();
		$composite = new CompositeGraphDatabasePlugin( $spy );

		$page1 = TestPage::build( id: 1 );
		$page2 = TestPage::build( id: 2 );
		$composite->savePage( $page1 );
		$composite->savePage( $page2 );
		$composite->deletePage( new PageId( 1 ) );

		$this->assertSame( [ $page1, $page2 ], $spy->savedPages );
		$this->assertCount( 1, $spy->deletedPageIds );
	}

	public function testSavePagePropagatesAPluginFailure(): void {
		$composite = new CompositeGraphDatabasePlugin( new ThrowingGraphDatabasePlugin() );

		$this->expectExceptionMessage( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE );

		$composite->savePage( TestPage::build() );
	}

	public function testDeletePagePropagatesAPluginFailure(): void {
		$composite = new CompositeGraphDatabasePlugin( new ThrowingGraphDatabasePlugin() );

		$this->expectExceptionMessage( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE );

		$composite->deletePage( new PageId( 1 ) );
	}

	/**
	 * The production hook-path wiring wraps each plugin in a FailureIsolatingGraphDatabasePlugin. This
	 * proves that arrangement isolates per plugin: a failing backend in the middle neither aborts the
	 * fan-out nor starves the plugins before or after it.
	 *
	 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\FailureIsolatingGraphDatabasePlugin
	 */
	public function testComposingIsolatedPluginsLetsOneFailWithoutStarvingTheOthers(): void {
		$spyBefore = new SpyGraphDatabasePlugin();
		$spyAfter = new SpyGraphDatabasePlugin();
		$composite = new CompositeGraphDatabasePlugin(
			$this->isolated( $spyBefore ),
			$this->isolated( new ThrowingGraphDatabasePlugin() ),
			$this->isolated( $spyAfter )
		);

		$page = TestPage::build( id: 42 );
		$pageId = new PageId( 42 );
		$composite->savePage( $page );
		$composite->deletePage( $pageId );

		$this->assertSame( [ $page ], $spyBefore->savedPages, 'a plugin before the failing one still runs' );
		$this->assertSame( [ $page ], $spyAfter->savedPages, 'a plugin after the failing one is not starved' );
		$this->assertSame( [ $pageId ], $spyBefore->deletedPageIds );
		$this->assertSame( [ $pageId ], $spyAfter->deletedPageIds );
	}

	private function isolated( GraphDatabasePlugin $plugin ): FailureIsolatingGraphDatabasePlugin {
		return new FailureIsolatingGraphDatabasePlugin( $plugin, new NullLogger() );
	}

}
