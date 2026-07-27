<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints;

use MediaWiki\Installer\DatabaseUpdater;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\ThrowingGraphDatabasePlugin;
use TestLogger;

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

	public function testASuccessfulUpdateReportsCompletion(): void {
		NeoWikiHooks::initializeGraphDatabases( $this->newUpdater() );

		$this->assertSame( "Initializing NeoWiki graph databases...done.\n", $this->report );
	}

	public function testUpdateReportsAFailingBackendInsteadOfAborting(): void {
		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin() );

		NeoWikiHooks::initializeGraphDatabases( $this->newUpdater() );

		$this->assertStringContainsString( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $this->report );
	}

	/**
	 * update.php --quiet discards everything written to the updater, so the report on its own leaves a
	 * wiki whose backends never initialized with no trace anywhere.
	 */
	public function testAFailingBackendIsAlsoLogged(): void {
		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );
		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin() );

		NeoWikiHooks::initializeGraphDatabases( $this->newUpdater() );

		$buffer = $logger->getBuffer();
		$this->assertCount( 1, $buffer );
		$this->assertSame( 'error', $buffer[0][0] );
		$this->assertStringContainsString( ThrowingGraphDatabasePlugin::FAILURE_MESSAGE, $buffer[0][1] );
	}

	/**
	 * A client reports an unreachable server by quoting the connection URI it tried, credentials and
	 * all, and this report goes to the operator's terminal and to deployment logs.
	 *
	 * @dataProvider credentialBearingMessageProvider
	 */
	public function testTheReportOfAFailingBackendCarriesNoCredentials( string $backendMessage, string $password ): void {
		$logger = new TestLogger( true );
		$this->setLogger( 'NeoWiki', $logger );
		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin( $backendMessage ) );

		NeoWikiHooks::initializeGraphDatabases( $this->newUpdater() );

		$this->assertStringNotContainsString( $password, $this->report );
		$this->assertStringContainsString( 'bolt://neo:7687', $this->report );
		$this->assertStringNotContainsString( $password, $logger->getBuffer()[0][1] );
	}

	/**
	 * A password is not limited to the characters that are safe in a URI, and the client quotes back
	 * whatever it was handed. Each shape below defeated a redaction anchored to the first `/` or `@`:
	 * the slash one leaked the password in full, the at-sign one leaked all but its first character.
	 *
	 * @return iterable<string, array{string, string}>
	 */
	public static function credentialBearingMessageProvider(): iterable {
		yield 'plain password' => [
			"Cannot connect to any server on alias: bolt with Uris: ('bolt://neo4j:sekrit@neo:7687')",
			'sekrit'
		];
		yield 'password containing a slash' => [
			'Unable to parse URI: bolt://neo4j:se/krit@neo:7687',
			'se/krit'
		];
		yield 'password containing an at sign' => [
			"Cannot connect to any server on alias: bolt with Uris: ('bolt://neo4j:se@krit@neo:7687')",
			'se@krit'
		];
		yield 'password containing a quote' => [
			"Cannot connect to any server on alias: bolt with Uris: ('bolt://neo4j:se'krit@neo:7687')",
			"se'krit"
		];
		yield 'two connection URIs in one message' => [
			"Cannot connect to any server on alias: bolt with Uris: ('bolt://neo4j:sekrit@neo:7687', "
				. "'neo4j+s://neo4j:sekrit@neo:7687')",
			'sekrit'
		];
	}

	/**
	 * A message that carries no credentials has to reach the operator intact: it is the only thing that
	 * says why the run failed, and the actionable causes — a duplicate id, a user without the rights to
	 * write schema — say so in prose rather than in a URI.
	 */
	public function testACredentialFreeReasonIsReportedVerbatim(): void {
		$reason = 'Both Node(162) and Node(163) have the label `Subject` and property `id` = "abc"';
		$this->registerGraphDatabasePlugins( new ThrowingGraphDatabasePlugin( $reason ) );

		NeoWikiHooks::initializeGraphDatabases( $this->newUpdater() );

		$this->assertStringContainsString( $reason, $this->report );
	}

	/**
	 * Records what is registered on it and what it is asked to output, into the fields of this test.
	 *
	 * getDB() returns the test database rather than the mock's null, because the registration test
	 * hands this double to every extension's LoadExtensionSchemaUpdates handler, and the ones that
	 * inspect the connection would otherwise fatal on code that has nothing to do with NeoWiki.
	 */
	private function newUpdater(): DatabaseUpdater {
		$updater = $this->createMock( DatabaseUpdater::class );

		$updater->method( 'getDB' )->willReturn( $this->getDb() );

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
