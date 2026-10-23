<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Maintenance;

use ProfessionalWiki\NeoWiki\Maintenance\Check;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\NeoWikiTables;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;

// The maintenance script is not PSR-4 autoloadable (it lives outside src/), so load it explicitly.
// Its RUN_MAINTENANCE_IF_MAIN guard is a no-op under PHPUnit, so this does not execute the script.
require_once __DIR__ . '/../../../maintenance/Check.php';

/**
 * Runs the script against the test wiki's database, with an extension directory and table list of the
 * test's own: whether CI built the frontend, or which tables a test run holds, is not up to the test.
 *
 * @covers \ProfessionalWiki\NeoWiki\Maintenance\Check
 * @group Database
 */
class CheckTest extends NeoWikiIntegrationTestCase {

	private const string MISSING_TABLE = 'neowiki_check_test_missing';

	private string $extensionDirectory;

	/**
	 * @var string[]
	 */
	private array $tableNames = NeoWikiTables::NAMES;

	private string $scriptOutput = '';

	protected function setUp(): void {
		parent::setUp();
		$this->extensionDirectory = $this->getNewTempDirectory();
		$this->buildFrontend();
	}

	protected function tearDown(): void {
		parent::tearDown();
		NeoWikiExtension::resetInstance();
	}

	public function testSucceedsWhenNothingIsMissing(): void {
		$this->registerNamedGraphDatabasePlugins( [ 'onsite' => new SpyGraphDatabasePlugin() ] );

		$this->assertTrue( $this->runCheck() );
	}

	public function testFailsWhenSomethingIsMissing(): void {
		$this->tableNames = [ self::MISSING_TABLE ];

		$this->assertFalse( $this->runCheck() );
	}

	public function testReportsAMissingFrontendBuild(): void {
		unlink( $this->extensionDirectory . '/resources/ext.neowiki/dist/neowiki.js' );

		$this->runCheck();

		$this->assertStringContainsString( 'resources/ext.neowiki/dist/neowiki.js', $this->scriptOutput );
	}

	public function testReportsMissingTables(): void {
		$this->tableNames = [ self::MISSING_TABLE ];

		$this->runCheck();

		$this->assertStringContainsString( self::MISSING_TABLE, $this->scriptOutput );
	}

	public function testReportsAGraphStoreThatCannotBeReached(): void {
		$this->registerNamedGraphDatabasePlugins( [ 'offsite' => new ThrowingGraphDatabasePlugin() ] );

		$this->runCheck();

		$this->assertStringContainsString( 'offsite', $this->scriptOutput );
		$this->assertStringContainsString( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $this->scriptOutput );
	}

	public function testFailsOnANeo4jConfigurationMissingOneUrl(): void {
		$succeeded = $this->runWithoutGraphBackend( function (): bool {
			$this->overrideConfigValue( 'NeoWikiNeo4jInternalReadUrl', 'bolt://neo:7687' );
			NeoWikiExtension::resetInstance();

			return $this->executeCheck();
		} );

		$this->assertFalse( $succeeded );
		$this->assertStringContainsString( '$wgNeoWikiNeo4jInternalWriteUrl', $this->scriptOutput );
	}

	public function testSucceedsWithoutGraphStores(): void {
		$this->assertTrue( $this->runCheck() );
	}

	private function buildFrontend(): void {
		mkdir( $this->extensionDirectory . '/resources/ext.neowiki/dist', 0777, true );
		touch( $this->extensionDirectory . '/resources/ext.neowiki/dist/neowiki.js' );
		touch( $this->extensionDirectory . '/resources/ext.neowiki/dist/neowiki.css' );
	}

	/**
	 * Without the bundled Neo4j backend, so that the only graph stores are those a test registers.
	 */
	private function runCheck(): bool {
		return $this->runWithoutGraphBackend( fn (): bool => $this->executeCheck() );
	}

	private function executeCheck(): bool {
		$script = new class( $this->extensionDirectory, $this->tableNames ) extends Check {

			/**
			 * @param string[] $tableNames
			 */
			public function __construct(
				private readonly string $extensionDirectory,
				private readonly array $tableNames,
			) {
				parent::__construct();
			}

			protected function getExtensionDirectory(): string {
				return $this->extensionDirectory;
			}

			protected function newTables(): NeoWikiTables {
				return new NeoWikiTables( $this->getReplicaDB(), $this->tableNames );
			}

		};
		$script->loadWithArgv( [] );

		ob_start();
		try {
			return $script->execute();
		} finally {
			$script->cleanupChanneled();
			$this->scriptOutput = (string)ob_get_clean();
		}
	}

}
