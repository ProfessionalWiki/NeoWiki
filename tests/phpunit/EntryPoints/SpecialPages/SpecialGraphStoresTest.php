<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\EntryPoints\SpecialPages;

use MediaWiki\Request\FauxRequest;
use MediaWiki\Session\CsrfTokenSet;
use MediaWiki\User\User;
use PermissionsError;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildStatus;
use ProfessionalWiki\NeoWiki\Domain\GraphRebuild\RebuildTrigger;
use ProfessionalWiki\NeoWiki\EntryPoints\NeoWikiRegistrar;
use ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialGraphStores;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\RebuildRunRepository;
use ProfessionalWiki\NeoWiki\Tests\HandlesNeo4jEnvOverrides;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\SpyGraphDatabasePlugin;
use SpecialPageTestBase;

/**
 * The page is rendered in qqx, so what is asserted is which message each cell and box uses rather than
 * what that message happens to say in English.
 *
 * @covers \ProfessionalWiki\NeoWiki\EntryPoints\SpecialPages\SpecialGraphStores
 * @group Database
 */
class SpecialGraphStoresTest extends SpecialPageTestBase {

	use HandlesNeo4jEnvOverrides;

	private const STORE = 'special-store';

	protected function setUp(): void {
		parent::setUp();

		$this->setTemporaryHook(
			'NeoWikiRegistration',
			static function ( NeoWikiRegistrar $registrar ): void {
				$registrar->addGraphDatabasePlugin( self::STORE, new SpyGraphDatabasePlugin() );
			}
		);
		NeoWikiExtension::resetInstance();
	}

	protected function tearDown(): void {
		parent::tearDown();
		NeoWikiExtension::resetInstance();
	}

	protected function newSpecialPage(): SpecialGraphStores {
		return new SpecialGraphStores();
	}

	public function testAUserWithoutTheAdminRightIsRefused(): void {
		$this->expectException( PermissionsError::class );

		$this->executeSpecialPage( '', null, null, $this->getTestUser()->getUser() );
	}

	public function testEachStoreIsListedWithHowFarItIsFromTheWiki(): void {
		[ $html ] = $this->executeSpecialPage( '', null, null, $this->newAdmin() );

		$this->assertStringContainsString( self::STORE, $html );
		$this->assertStringContainsString( 'neowiki-graphstores-state-neverbuilt', $html );
		$this->assertStringContainsString( 'neowiki-graphstores-lastrebuild-never', $html );
		$this->assertStringContainsString( 'neowiki-graphstores-rebuild', $html );
	}

	public function testRebuildingQueuesARun(): void {
		$this->postAction( 'rebuild', $this->newAdmin() );

		$this->assertSame( RebuildStatus::Queued, $this->newRunRepository()->getActiveRun( self::STORE )?->status );
	}

	/**
	 * @dataProvider outcomeProvider
	 */
	public function testWhatEachActionDidIsCarriedToThePageItRedirectsTo(
		string $action,
		?string $storeName,
		bool $startFirst,
		string $expectedOutcome
	): void {
		$admin = $this->newAdmin();

		if ( $startFirst ) {
			$this->postAction( 'rebuild', $admin );
		}

		[ , $response ] = $this->postAction( $action, $admin, $storeName );

		$this->assertStringContainsString( 'outcome=' . $expectedOutcome, (string)$response->getHeader( 'Location' ) );
	}

	public function outcomeProvider(): iterable {
		yield 'a rebuild is queued' => [ 'rebuild', null, false, 'queued' ];
		yield 'a rebuild is cancelled' => [ 'cancel', null, true, 'cancelled' ];
		yield 'a second rebuild is refused' => [ 'rebuild', null, true, 'alreadyrunning' ];
		yield 'a store with no rebuild cannot be cancelled' => [ 'cancel', null, false, 'nothingtocancel' ];
		yield 'an unconfigured store cannot be rebuilt' => [ 'rebuild', 'no-such-store', false, 'unknownstore' ];
	}

	public function testAFormWithoutAValidTokenSaysTheSessionExpired(): void {
		$request = new FauxRequest(
			[ 'nwAction' => 'rebuild', 'nwStore' => self::STORE, 'wpEditToken' => 'not-the-token' ],
			true
		);

		[ , $response ] = $this->executeSpecialPage( '', $request, null, $this->newAdmin() );

		$this->assertStringContainsString( 'outcome=sessionfailure', (string)$response->getHeader( 'Location' ) );
	}

	public function testARunningRebuildIsShownWithWhatItHasGotThrough(): void {
		$repository = $this->newRunRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Api, RebuildStatus::Running );
		$repository->updateRun( $run->withProgress( cursor: 40, processed: 37, failed: 2 ) );

		[ $html ] = $this->executeSpecialPage( '', null, null, $this->newAdmin() );

		$this->assertStringContainsString( 'neowiki-graphstores-state-running: 37, 2', $html );
	}

	public function testARebuiltStoreIsShownWithWhenItWasRebuiltAndWhatThatGotThrough(): void {
		$repository = $this->newRunRepository();
		$run = $repository->startRun( self::STORE, RebuildTrigger::Cli, RebuildStatus::Running );
		$repository->updateRun( $run->withProgress( cursor: 40, processed: 40, failed: 0 )->succeeded() );

		[ $html ] = $this->executeSpecialPage( '', null, null, $this->newAdmin() );

		$this->assertStringContainsString( 'neowiki-graphstores-state-insync', $html );
		$this->assertStringContainsString( 'neowiki-graphstores-lastrebuild:', $html );
	}

	public function testTheQueuedRebuildIsWhatTheTableThenShows(): void {
		$admin = $this->newAdmin();
		$this->postAction( 'rebuild', $admin );

		[ $html ] = $this->executeSpecialPage( '', null, null, $admin );

		$this->assertStringContainsString( 'neowiki-graphstores-state-queued', $html );
		$this->assertStringContainsString( 'neowiki-graphstores-cancel', $html );
	}

	public function testCancellingStopsTheQueuedRebuild(): void {
		$admin = $this->newAdmin();
		$this->postAction( 'rebuild', $admin );

		$this->postAction( 'cancel', $admin );

		$this->assertNull( $this->newRunRepository()->getActiveRun( self::STORE ) );
	}

	/**
	 * Without the redirect, a browser reloading to watch a rebuild advance would repeat the submission
	 * that started it.
	 */
	public function testAnActionRendersNothingOfItsOwnBecauseItRedirects(): void {
		[ $html ] = $this->postAction( 'rebuild', $this->newAdmin() );

		$this->assertSame( '', $html );
	}

	public function testWhatAnActionDidIsReportedOnThePageItRedirectsTo(): void {
		[ $html ] = $this->executeSpecialPage( '', $this->newOutcomeRequest( 'queued' ), null, $this->newAdmin() );

		$this->assertStringContainsString( 'neowiki-graphstores-outcome-queued', $html );
	}

	public function testAFormWithoutAValidTokenChangesNothing(): void {
		$request = new FauxRequest(
			[ 'nwAction' => 'rebuild', 'nwStore' => self::STORE, 'wpEditToken' => 'not-the-token' ],
			true
		);

		$this->executeSpecialPage( '', $request, null, $this->newAdmin() );

		$this->assertNull( $this->newRunRepository()->getActiveRun( self::STORE ) );
	}

	/**
	 * A store name is whatever configuration says, and the page renders it back into a form field.
	 */
	public function testAStoreNameCarryingMarkupIsEscaped(): void {
		$this->clearHook( 'NeoWikiRegistration' );
		$this->setTemporaryHook(
			'NeoWikiRegistration',
			static function ( NeoWikiRegistrar $registrar ): void {
				$registrar->addGraphDatabasePlugin( '"><script>alert(1)</script>', new SpyGraphDatabasePlugin() );
			}
		);
		NeoWikiExtension::resetInstance();

		[ $html ] = $this->executeSpecialPage( '', null, null, $this->newAdmin() );

		$this->assertStringNotContainsString( '<script>', $html );
		$this->assertStringContainsString( '&lt;script&gt;', $html );
	}

	public function testRebuildingAStoreThisWikiHasNotConfiguredChangesNothing(): void {
		$this->postAction( 'rebuild', $this->newAdmin(), 'no-such-store' );

		$this->assertNull( $this->newRunRepository()->getLatestRun( 'no-such-store' ) );
	}

	public function testAWikiWithNoGraphStoresIsToldSoInsteadOfShownAnEmptyTable(): void {
		$this->clearHook( 'NeoWikiRegistration' );
		$this->snapshotAndClearNeo4jEnvOverrides();
		$this->overrideConfigValues( [
			'NeoWikiNeo4jInternalWriteUrl' => null,
			'NeoWikiNeo4jInternalReadUrl' => null,
			'NeoWikiSparqlStores' => [],
		] );
		NeoWikiExtension::resetInstance();

		try {
			[ $html ] = $this->executeSpecialPage( '', null, null, $this->newAdmin() );
		} finally {
			$this->restoreNeo4jEnvOverrides();
		}

		$this->assertStringContainsString( 'neowiki-graphstores-nostores', $html );
	}

	/**
	 * Signs the request the way a form rendered by this page would be: with the token of the session that
	 * request carries, which is what the page checks it against.
	 *
	 * @return array{0: string, 1: mixed}
	 */
	private function postAction( string $action, User $performer, ?string $storeName = null ): array {
		$request = new FauxRequest( [ 'nwAction' => $action, 'nwStore' => $storeName ?? self::STORE ], true );
		$request->setVal( 'wpEditToken', ( new CsrfTokenSet( $request ) )->getToken()->toString() );

		return $this->executeSpecialPage( '', $request, null, $performer );
	}

	private function newOutcomeRequest( string $outcome ): FauxRequest {
		return new FauxRequest( [ 'outcome' => $outcome, 'store' => self::STORE ] );
	}

	private function newAdmin(): User {
		return $this->getTestUser( [ 'sysop' ] )->getUser();
	}

	private function newRunRepository(): RebuildRunRepository {
		return NeoWikiExtension::getInstance()->newRebuildRunRepository();
	}

}
