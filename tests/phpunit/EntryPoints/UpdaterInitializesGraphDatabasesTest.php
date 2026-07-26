<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Installer\DatabaseUpdater;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;

/**
 * Guards the update.php path: an ordinary install or upgrade must establish the store-level structures
 * the backends need, since the incremental per-edit projection creates none and RebuildGraphDatabases
 * is not part of that routine. The Neo4j uniqueness constraints are what the projection's id lookups
 * seek on, so a wiki that never got them pays a scan per lookup.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::onLoadExtensionSchemaUpdates
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks::initializeGraphDatabases
 * @group Database
 */
class UpdaterInitializesGraphDatabasesTest extends NeoWikiIntegrationTestCase {

	/**
	 * @var mixed[] The updates registered on the updater built by newUpdater().
	 */
	private array $registeredUpdates;

	private string $report;

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->registeredUpdates = [];
		$this->report = '';
	}

	protected function tearDown(): void {
		parent::tearDown();
		// Some tests rebuild the singleton with an extra plugin registered; reset it so later tests get
		// a clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	/**
	 * Runs the hook the way MediaWiki does, through the registration in extension.json, so that losing
	 * that registration fails here rather than silently disconnecting update.php from the graph stores.
	 */
	public function testTheSchemaUpdaterHookRegistersGraphDatabaseInitialization(): void {
		$this->getServiceContainer()->getHookContainer()->run(
			'LoadExtensionSchemaUpdates',
			[ $this->newUpdater() ]
		);

		$this->assertContains( [ [ NeoWikiHooks::class, 'initializeGraphDatabases' ] ], $this->registeredUpdates );
	}

	public function testUpdateCreatesTheDefaultConstraints(): void {
		NeoWikiHooks::initializeGraphDatabases( $this->newUpdater() );

		$this->assertSame( [ 'Page wiki_id id', 'Subject id' ], $this->constraintNames() );
	}

	public function testUpdateReportsAFailingBackendInsteadOfAborting(): void {
		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin() );

		NeoWikiHooks::initializeGraphDatabases( $this->newUpdater() );

		$this->assertStringContainsString( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $this->report );
	}

	/**
	 * A client reports an unreachable server by quoting the connection URI it tried, credentials and
	 * all, and this report goes to the operator's terminal and to deployment logs.
	 */
	public function testTheReportOfAFailingBackendCarriesNoCredentials(): void {
		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin(
			"Cannot connect to any server on alias: bolt with Uris: ('bolt://neo4j:sekrit@neo:7687')"
		) );

		NeoWikiHooks::initializeGraphDatabases( $this->newUpdater() );

		$this->assertStringNotContainsString( 'sekrit', $this->report );
		$this->assertStringContainsString( 'bolt://neo:7687', $this->report );
	}

	/**
	 * Records what is registered on it and what it is asked to output, into the fields of this test.
	 */
	private function newUpdater(): DatabaseUpdater {
		$updater = $this->createMock( DatabaseUpdater::class );

		$updater->method( 'addExtensionUpdate' )->willReturnCallback(
			function ( array $update ): void {
				$this->registeredUpdates[] = $update;
			}
		);

		$updater->method( 'output' )->willReturnCallback(
			function ( string $text ): void {
				$this->report .= $text;
			}
		);

		return $updater;
	}

	/**
	 * The constraints themselves are specified by Neo4jConstraintUpdaterTest; their names are asserted
	 * here only to show that this path reached them.
	 *
	 * @return string[]
	 */
	private function constraintNames(): array {
		return array_map(
			static fn ( $record ) => $record->get( 'name' ),
			$this->readGraph( 'SHOW CONSTRAINTS YIELD name ORDER BY name' )->toArray()
		);
	}

}
