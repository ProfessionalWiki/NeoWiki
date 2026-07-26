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

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
	}

	protected function tearDown(): void {
		parent::tearDown();
		// One test rebuilds the singleton with an extra plugin registered; reset it so later tests get
		// a clean instance rebuilt without the temporary hook.
		NeoWikiExtension::resetInstance();
	}

	public function testUpdaterRegistersGraphDatabaseInitialization(): void {
		$updater = $this->recordingUpdater( $updates );

		NeoWikiHooks::onLoadExtensionSchemaUpdates( $updater );

		$this->assertContains( [ [ NeoWikiHooks::class, 'initializeGraphDatabases' ] ], $updates );
	}

	public function testUpdateCreatesTheDefaultConstraints(): void {
		NeoWikiHooks::initializeGraphDatabases( $this->reportingUpdater( $report ) );

		$this->assertSame( [ 'Page wiki_id id', 'Subject id' ], $this->constraintNames() );
	}

	public function testUpdateReportsAnUnreachableBackendInsteadOfAborting(): void {
		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin() );

		NeoWikiHooks::initializeGraphDatabases( $this->reportingUpdater( $report ) );

		$this->assertStringContainsString( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $report );
	}

	/**
	 * @param mixed[]|null $updates Filled with the updates registered on the returned updater.
	 */
	private function recordingUpdater( ?array &$updates ): DatabaseUpdater {
		$updates = [];
		$updater = $this->createMock( DatabaseUpdater::class );
		$updater->method( 'addExtensionUpdate' )->willReturnCallback(
			static function ( array $update ) use ( &$updates ): void {
				$updates[] = $update;
			}
		);

		return $updater;
	}

	/**
	 * @param string|null $report Filled with everything the returned updater was asked to output.
	 */
	private function reportingUpdater( ?string &$report ): DatabaseUpdater {
		$report = '';
		$updater = $this->createMock( DatabaseUpdater::class );
		$updater->method( 'output' )->willReturnCallback(
			static function ( string $text ) use ( &$report ): void {
				$report .= $text;
			}
		);

		return $updater;
	}

	/**
	 * @return string[]
	 */
	private function constraintNames(): array {
		return array_map(
			static fn ( $record ) => $record->get( 'name' ),
			$this->readGraph( 'SHOW CONSTRAINTS YIELD name ORDER BY name' )->toArray()
		);
	}

}
