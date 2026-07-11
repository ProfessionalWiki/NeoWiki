<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\GraphDatabase;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\FailureIsolatingGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\Page\Page;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestPage;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use Psr\Log\Test\TestLogger;
use Throwable;
use Wikimedia\Rdbms\DBError;
use Wikimedia\RequestTimeout\TimeoutException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\GraphDatabase\FailureIsolatingGraphDatabasePlugin
 */
class FailureIsolatingGraphDatabasePluginTest extends TestCase {

	private TestLogger $logger;

	protected function setUp(): void {
		parent::setUp();
		$this->logger = new TestLogger();
	}

	private function newDecorator( GraphDatabasePlugin $plugin ): FailureIsolatingGraphDatabasePlugin {
		return new FailureIsolatingGraphDatabasePlugin( $plugin, $this->logger );
	}

	public function testSavePageIsDelegatedToTheWrappedPlugin(): void {
		$spy = new SpyGraphDatabasePlugin();
		$page = TestPage::build( id: 42 );

		$this->newDecorator( $spy )->savePage( $page );

		$this->assertSame( [ $page ], $spy->savedPages );
	}

	public function testDeletePageIsDelegatedToTheWrappedPlugin(): void {
		$spy = new SpyGraphDatabasePlugin();
		$pageId = new PageId( 42 );

		$this->newDecorator( $spy )->deletePage( $pageId );

		$this->assertSame( [ $pageId ], $spy->deletedPageIds );
	}

	public function testSucceedingProjectionLogsNothing(): void {
		$this->newDecorator( new SpyGraphDatabasePlugin() )->savePage( TestPage::build( id: 42 ) );

		$this->assertSame( [], $this->logger->records );
	}

	public function testFailingSaveIsSwallowedAndLoggedWithAnActionableMessage(): void {
		$this->newDecorator( new ThrowingGraphDatabasePlugin() )->savePage( TestPage::build( id: 42 ) );

		$this->assertTrue( $this->logger->hasErrorRecords(), 'the failure is logged at error level' );

		$message = $this->logger->records[0]['message'];
		$this->assertStringContainsString( 'save', $message );
		$this->assertStringContainsString( '42', $message );
		$this->assertStringContainsString( ThrowingGraphDatabasePlugin::class, $message, 'names the failing backend' );
		$this->assertStringContainsString( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $message, 'carries the underlying error' );
		$this->assertStringContainsString( 'RebuildGraphDatabases', $message, 'points at the reconciliation script' );
		$this->assertStringContainsString( 'triggering operation', $message, 'stays operation-neutral' );
	}

	public function testFailingDeleteIsSwallowedAndLoggedForTheDeleteOperation(): void {
		$this->newDecorator( new ThrowingGraphDatabasePlugin() )->deletePage( new PageId( 42 ) );

		$this->assertTrue( $this->logger->hasErrorRecords() );

		$message = $this->logger->records[0]['message'];
		$this->assertStringContainsString( 'delete', $message, 'reflects the actual operation, not a hardcoded edit/save' );
		$this->assertStringContainsString( '42', $message );
		$this->assertStringContainsString( 'RebuildGraphDatabases', $message );
	}

	public function testRequestTimeoutIsReThrownRatherThanSwallowed(): void {
		$decorator = $this->newDecorator( $this->pluginThrowing( new TimeoutException( 'request exceeded the limit', 30.0 ) ) );

		$this->expectException( TimeoutException::class );

		$decorator->savePage( TestPage::build() );
	}

	public function testDatabaseErrorIsReThrownRatherThanSwallowed(): void {
		$decorator = $this->newDecorator( $this->pluginThrowing( new DBError( null, 'the wiki database is down' ) ) );

		$this->expectException( DBError::class );

		$decorator->deletePage( new PageId( 1 ) );
	}

	private function pluginThrowing( Throwable $error ): GraphDatabasePlugin {
		return new class( $error ) implements GraphDatabasePlugin {

			public function __construct(
				private readonly Throwable $error
			) {
			}

			public function savePage( Page $page ): void {
				throw $this->error;
			}

			public function deletePage( PageId $pageId ): void {
				throw $this->error;
			}

		};
	}

}
