<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\GraphRebuild;

use MediaWiki\MediaWikiServices;
use MediaWiki\Title\Title;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\GraphRebuildCoordinator;
use ProfessionalWiki\NeoWiki\Application\GraphRebuild\MappingChangeRebuilder;
use ProfessionalWiki\NeoWiki\Domain\GraphDatabase\GraphDatabasePlugin;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildRun;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use MediaWiki\Deferred\DeferredUpdates;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyRebuildJobQueue;
use Psr\Log\NullLogger;
use TestLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\GraphRebuild\MappingChangeRebuilder
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiHooks
 * @group Database
 */
class MappingChangeRebuildTest extends NeoWikiIntegrationTestCase {

	private const MAPPED_STORE = 'EDM';
	private const PROJECTION = 'EDM';
	private const OTHER_STORE = 'CIDOC';
	private const MAPPING_JSON = '{"version":1,"schemas":{}}';

	/**
	 * Two SPARQL stores holding different ontology projections, which is the only kind of store a Mapping
	 * page defines the contents of. Their endpoints are never reached: queueing a rebuild writes a row and
	 * files a job, and no job is run here.
	 */
	protected function setUp(): void {
		parent::setUp();
		$this->overrideConfigValues( [
			'NeoWikiAutoRebuildOnMappingChange' => true,
			'NeoWikiSparqlStores' => [
				[ 'updateUrl' => 'http://sparql.invalid/edm', 'projection' => self::PROJECTION, 'name' => self::MAPPED_STORE ],
				[ 'updateUrl' => 'http://sparql.invalid/cidoc', 'projection' => 'CIDOC', 'name' => self::OTHER_STORE ],
			],
		] );
		NeoWikiExtension::resetInstance();
	}

	protected function tearDown(): void {
		parent::tearDown();
		NeoWikiExtension::resetInstance();
	}

	public function testSavingAMappingQueuesARebuildOfTheStoreHoldingItsProjection(): void {
		$this->createMapping( self::PROJECTION, self::MAPPING_JSON );

		$run = $this->activeRunOf( self::MAPPED_STORE );

		$this->assertSame( RebuildStatus::Queued, $run?->status );
		$this->assertSame( RebuildTrigger::Auto, $run->trigger );
	}

	/**
	 * A projection is a Mapping page name, whose first letter MediaWiki capitalises, so configuration
	 * naming it in lower case still matches an edit to that page.
	 */
	public function testAProjectionConfiguredInLowerCaseMatchesItsMappingPage(): void {
		$this->overrideConfigValue( 'NeoWikiSparqlStores', [
			[ 'updateUrl' => 'http://sparql.invalid/edm', 'projection' => 'edm', 'name' => self::MAPPED_STORE ],
		] );
		NeoWikiExtension::resetInstance();

		$this->createMapping( 'Edm', self::MAPPING_JSON );

		$this->assertSame( RebuildStatus::Queued, $this->activeRunOf( self::MAPPED_STORE )?->status );
	}

	public function testAStoreHoldingAnotherProjectionIsLeftAlone(): void {
		$this->createMapping( self::PROJECTION, self::MAPPING_JSON );

		$this->assertNull( $this->activeRunOf( self::OTHER_STORE ) );
	}

	public function testDeletingAMappingQueuesARebuildOfTheStoreHoldingItsProjection(): void {
		$this->createMapping( self::PROJECTION, self::MAPPING_JSON );
		$this->newRunRepository()->updateRun( $this->activeRunOf( self::MAPPED_STORE )->cancelled() );

		$this->deleteMapping( self::PROJECTION );

		$this->assertSame( RebuildStatus::Queued, $this->activeRunOf( self::MAPPED_STORE )?->status );
	}

	/**
	 * A run that began under the old Mapping has projected part of the wiki under rules that no longer
	 * apply, so it is restarted rather than left to finish half in each vocabulary.
	 */
	public function testEditingAMappingRestartsTheRebuildItsStoreAlreadyHadGoing(): void {
		$this->createMapping( self::PROJECTION, self::MAPPING_JSON );
		$firstRun = $this->activeRunOf( self::MAPPED_STORE );

		$this->createMapping( self::PROJECTION, '{"version":1,"prefixes":{},"schemas":{}}' );

		$repository = $this->newRunRepository();
		$this->assertSame( RebuildStatus::Cancelled, $repository->getRun( $firstRun->id )?->status );
		$this->assertNotSame( $firstRun->id, $this->activeRunOf( self::MAPPED_STORE )?->id );
	}

	public function testEditingAPageThatIsNotAMappingRebuildsNothing(): void {
		$this->editPage( 'A plain page', 'Nothing to do with mappings.' );

		$this->assertNull( $this->activeRunOf( self::MAPPED_STORE ) );
		$this->assertNull( $this->activeRunOf( self::OTHER_STORE ) );
	}

	public function testNothingIsRebuiltWhenTheWikiHasNotAskedForIt(): void {
		$this->overrideConfigValue( 'NeoWikiAutoRebuildOnMappingChange', false );

		$this->createMapping( self::PROJECTION, self::MAPPING_JSON );

		$this->assertNull( $this->activeRunOf( self::MAPPED_STORE ) );
	}

	public function testDeletingAMappingRebuildsNothingWhenTheWikiHasNotAskedForIt(): void {
		$this->overrideConfigValue( 'NeoWikiAutoRebuildOnMappingChange', false );
		$this->createMapping( self::PROJECTION, self::MAPPING_JSON );

		$this->deleteMapping( self::PROJECTION );

		$this->assertNull( $this->activeRunOf( self::MAPPED_STORE ) );
	}

	/**
	 * A rebuild started from anywhere else is replaced rather than refused alongside, for the same reason
	 * an automatic one is: it began under rules that have since changed.
	 */
	public function testAMappingChangeReplacesARebuildThatWasAlreadyRunning(): void {
		$runningRun = $this->newRunRepository()
			->startRun( self::MAPPED_STORE, RebuildTrigger::Cli, RebuildStatus::Running );

		$this->createMapping( self::PROJECTION, self::MAPPING_JSON );

		$activeRun = $this->activeRunOf( self::MAPPED_STORE );
		$this->assertSame( RebuildStatus::Queued, $activeRun?->status );
		$this->assertNotSame( $runningRun->id, $activeRun->id );
	}

	/**
	 * The Mapping has been saved by the time this runs, so a store that cannot be rebuilt must be reported
	 * rather than allowed to take the edit down with it.
	 */
	public function testAStoreThatCannotBeRebuiltIsReportedRatherThanThrownAt(): void {
		$logger = new TestLogger( true );
		$extension = NeoWikiExtension::getInstance();
		$rebuilder = new MappingChangeRebuilder(
			projectionsByStore: [ self::MAPPED_STORE => self::PROJECTION ],
			coordinator: new GraphRebuildCoordinator(
				stores: $extension->getNamedGraphDatabasePlugins(),
				runs: $extension->newRebuildRunRepository(),
				startLock: $extension->newRebuildStartLock(),
				executor: $extension->newGraphRebuildExecutor(),
				jobQueue: SpyRebuildJobQueue::refusingEverything(),
				newPageRebuilder: static fn ( GraphDatabasePlugin $store ) => $extension->newSubjectPageRebuilderFor( $store ),
				logger: new NullLogger(),
			),
			logger: $logger,
		);

		$rebuilder->onMappingChanged( self::PROJECTION );

		$this->assertStringContainsString(
			'could not rebuild graph store "' . self::MAPPED_STORE . '"',
			implode( "\n", array_map( static fn ( array $record ): string => $record[1], $logger->getBuffer() ) )
		);
	}

	/**
	 * The rebuild is started after the change's own transaction, which a request does once it has
	 * answered. Nothing has answered here, so the deferred work is run before the records are read.
	 */
	private function activeRunOf( string $storeName ): ?RebuildRun {
		DeferredUpdates::doUpdates();

		return $this->newRunRepository()->getActiveRun( $storeName );
	}

	private function newRunRepository(): RebuildRunRepository {
		return NeoWikiExtension::getInstance()->newRebuildRunRepository();
	}

	private function deleteMapping( string $name ): void {
		$page = MediaWikiServices::getInstance()->getWikiPageFactory()->newFromTitle(
			Title::newFromText( $name, NeoWikiExtension::NS_MAPPING )
		);
		$deletePage = MediaWikiServices::getInstance()->getDeletePageFactory()->newDeletePage(
			$page,
			$this->getTestSysop()->getUser()
		);

		$this->assertStatusGood( $deletePage->deleteUnsafe( 'test deletion' ) );
	}

}
