<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Persistence\CompositeGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\CompositeGraphDatabasePlugin
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

}
