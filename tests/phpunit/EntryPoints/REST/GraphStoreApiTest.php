<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\REST;

use MediaWiki\Rest\Handler;
use MediaWiki\Rest\RequestData;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\CancelGraphStoreRebuildApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\GetGraphStoresApi;
use ProfessionalWiki\NeoWiki\EntryPoints\REST\StartGraphStoreRebuildApi;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use ProfessionalWiki\NeoWiki\Presentation\CsrfValidator;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;

/**
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\CancelGraphStoreRebuildApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GetGraphStoresApi
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\GraphStoreAdminAccess
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\REST\StartGraphStoreRebuildApi
 * @covers \ProfessionalWiki\NeoWiki\Presentation\GraphStoreStatusSerializer
 * @group Database
 */
class GraphStoreApiTest extends NeoWikiIntegrationTestCase {
	use HandlerTestTrait;

	private const STORE = 'rest-store';

	protected function setUp(): void {
		parent::setUp();
		$this->registerNamedGraphDatabasePlugins( [ self::STORE => new SpyGraphDatabasePlugin() ] );
	}

	protected function tearDown(): void {
		parent::tearDown();
		NeoWikiExtension::resetInstance();
	}

	public function testListingTheStoresWithoutTheAdminRightIsRefused(): void {
		$response = $this->executeAsReader( new GetGraphStoresApi(), 'GET' );

		$this->assertSame( 403, $response['status'] );
		$this->assertSame( 'permissionDenied', $response['body']['errorType'] );
	}

	public function testStartingARebuildWithoutTheAdminRightIsRefused(): void {
		$response = $this->executeAsReader( $this->newStartApi(), 'POST', self::STORE );

		$this->assertSame( 403, $response['status'] );
	}

	public function testCancellingARebuildWithoutTheAdminRightIsRefused(): void {
		$response = $this->executeAsReader( $this->newCancelApi(), 'DELETE', self::STORE );

		$this->assertSame( 403, $response['status'] );
	}

	public function testAStoreNothingHasRebuiltIsReportedAsNeverBuilt(): void {
		$response = $this->executeAsAdmin( new GetGraphStoresApi(), 'GET' );

		$store = self::storeNamed( $response['body']['stores'], self::STORE );
		$this->assertSame( 'never-built', $store['state'] );
		$this->assertNull( $store['projection'], 'a backend holding no RDF has no projection' );
		$this->assertNull( $store['activeRun'] );
		$this->assertNull( $store['lastSuccessfulRun'] );
	}

	public function testARebuiltStoreReportsWhatThatRebuildGotThrough(): void {
		$repository = $this->newRunRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $run->withProgress( cursor: 9, processed: 8, failed: 1 )->succeeded() );

		$response = $this->executeAsAdmin( new GetGraphStoresApi(), 'GET' );

		$store = self::storeNamed( $response['body']['stores'], self::STORE );
		$this->assertSame( 'in-sync', $store['state'] );
		$this->assertSame( 8, $store['lastSuccessfulRun']['processed'] );
		$this->assertSame( 1, $store['lastSuccessfulRun']['failed'] );
		$this->assertMatchesRegularExpression(
			'/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}Z$/',
			(string)$store['lastSuccessfulRun']['finished'],
			'times are reported as ISO 8601, not as the wiki stores them'
		);
	}

	/**
	 * A backend reports an unreachable server by quoting the endpoint it tried, and the run keeps that
	 * message for whoever reads the records. That is not the same audience as whoever may call this.
	 */
	public function testAReportedRunDoesNotCarryWhyAnEarlierOneFailed(): void {
		$response = $this->executeAsAdmin( $this->newStartApi(), 'POST', self::STORE );

		$this->assertArrayNotHasKey( 'error', $response['body'] );
	}

	public function testStartingARebuildQueuesItAndReportsTheRun(): void {
		$response = $this->executeAsAdmin( $this->newStartApi(), 'POST', self::STORE );

		$this->assertSame( 202, $response['status'] );
		$this->assertSame( self::STORE, $response['body']['store'] );
		$this->assertSame( 'queued', $response['body']['status'] );
		$this->assertSame( 'api', $response['body']['trigger'] );
		$this->assertSame( 'pages', $response['body']['phase'] );
	}

	public function testTheQueuedRebuildShowsUpAsTheStoresActiveRun(): void {
		$this->executeAsAdmin( $this->newStartApi(), 'POST', self::STORE );

		$response = $this->executeAsAdmin( new GetGraphStoresApi(), 'GET' );

		$store = self::storeNamed( $response['body']['stores'], self::STORE );
		$this->assertSame( 'queued', $store['activeRun']['status'] );
		$this->assertSame( 0, $store['activeRun']['processed'] );
	}

	public function testStartingARebuildOfAStoreThatIsAlreadyRebuildingIsRefused(): void {
		$this->executeAsAdmin( $this->newStartApi(), 'POST', self::STORE );

		$response = $this->executeAsAdmin( $this->newStartApi(), 'POST', self::STORE );

		$this->assertSame( 409, $response['status'] );
		$this->assertSame( 'rebuildAlreadyRunning', $response['body']['errorType'] );
	}

	public function testStartingARebuildOfAStoreThisWikiHasNotConfiguredIsRefused(): void {
		$response = $this->executeAsAdmin( $this->newStartApi(), 'POST', 'no-such-store' );

		$this->assertSame( 404, $response['status'] );
		$this->assertSame( 'unknownStore', $response['body']['errorType'] );
	}

	public function testCancellingReportsTheRunItStopped(): void {
		$started = $this->executeAsAdmin( $this->newStartApi(), 'POST', self::STORE );

		$response = $this->executeAsAdmin( $this->newCancelApi(), 'DELETE', self::STORE );

		$this->assertSame( 200, $response['status'] );
		$this->assertSame( $started['body']['id'], $response['body']['id'] );
		$this->assertSame( 'cancelled', $response['body']['status'] );
	}

	public function testCancellingAStoreWithNoRebuildIsRefused(): void {
		$response = $this->executeAsAdmin( $this->newCancelApi(), 'DELETE', self::STORE );

		$this->assertSame( 404, $response['status'] );
		$this->assertSame( 'noRebuildToCancel', $response['body']['errorType'] );
	}

	public function testCancellingAStoreThisWikiHasNotConfiguredIsRefused(): void {
		$response = $this->executeAsAdmin( $this->newCancelApi(), 'DELETE', 'no-such-store' );

		$this->assertSame( 404, $response['status'] );
		$this->assertSame( 'unknownStore', $response['body']['errorType'] );
	}

	/**
	 * The right to rebuild a store is not the right to read the credentials for it, and these responses
	 * are the one place a store's configuration could leak out of.
	 */
	public function testTheReportedStoresCarryNoEndpointOrToken(): void {
		$this->overrideConfigValue( 'NeoWikiSparqlStores', [ [
			'updateUrl' => 'https://qlever.example/api/neowiki',
			'accessToken' => 'sekrit',
			'projection' => 'EDM',
			'name' => 'EDM',
		] ] );
		NeoWikiExtension::resetInstance();

		$response = $this->executeAsAdmin( new GetGraphStoresApi(), 'GET' );
		$body = json_encode( $response['body'] );

		$this->assertStringContainsString( '"EDM"', (string)$body, 'the store itself is reported' );
		$this->assertStringNotContainsString( 'sekrit', (string)$body );
		$this->assertStringNotContainsString( 'qlever.example', (string)$body );
	}

	/**
	 * @return array{status: int, body: array<string, mixed>}
	 */
	private function executeAsAdmin( Handler $handler, string $method, ?string $storeName = null ): array {
		return $this->execute( $handler, $method, $storeName, [ 'neowiki-admin' ] );
	}

	/**
	 * @return array{status: int, body: array<string, mixed>}
	 */
	private function executeAsReader( Handler $handler, string $method, ?string $storeName = null ): array {
		return $this->execute( $handler, $method, $storeName, [] );
	}

	/**
	 * @param string[] $rights
	 * @return array{status: int, body: array<string, mixed>}
	 */
	private function execute( Handler $handler, string $method, ?string $storeName, array $rights ): array {
		$response = $this->executeHandler(
			$handler,
			new RequestData( [
				'method' => $method,
				'pathParams' => $storeName === null ? [] : [ 'name' => $storeName ],
			] ),
			[],
			[],
			[],
			[],
			$this->mockAnonAuthorityWithPermissions( $rights )
		);

		return [
			'status' => $response->getStatusCode(),
			'body' => (array)json_decode( $response->getBody()->getContents(), true ),
		];
	}

	private function newStartApi(): StartGraphStoreRebuildApi {
		return new StartGraphStoreRebuildApi( csrfValidator: $this->newPassingCsrfValidator() );
	}

	private function newCancelApi(): CancelGraphStoreRebuildApi {
		return new CancelGraphStoreRebuildApi( csrfValidator: $this->newPassingCsrfValidator() );
	}

	private function newPassingCsrfValidator(): CsrfValidator {
		$validator = $this->createStub( CsrfValidator::class );
		$validator->method( 'verifyCsrfToken' )->willReturn( true );

		return $validator;
	}

	/**
	 * @param array<int, array<string, mixed>> $stores
	 * @return array<string, mixed>
	 */
	private static function storeNamed( array $stores, string $name ): array {
		foreach ( $stores as $store ) {
			if ( $store['name'] === $name ) {
				return $store;
			}
		}

		self::fail( 'The response does not report a store named ' . $name . '.' );
	}

	private function newRunRepository(): RebuildRunRepository {
		return NeoWikiExtension::getInstance()->newRebuildRunRepository();
	}

}
