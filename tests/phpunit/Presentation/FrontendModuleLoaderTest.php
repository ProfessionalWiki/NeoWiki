<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use MediaWiki\Context\RequestContext;
use MediaWiki\Output\OutputPage;
use MediaWiki\Permissions\Authority;
use MediaWiki\Permissions\PermissionStatus;
use MediaWiki\Permissions\SimpleAuthority;
use MediaWiki\Title\Title;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Presentation\FrontendModuleLoader;
use Skin;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\FrontendModuleLoader
 * @group Database
 */
class FrontendModuleLoaderTest extends MediaWikiIntegrationTestCase {

	/** @var array<int, string> */
	private array $addedModules = [];

	/** @var array<int, string> */
	private array $addedModuleStyles = [];

	/** @var array<string, mixed> */
	private array $addedJsConfigVars = [];

	public function testAddsCoreModuleWhenNoExtensionsHandleHook(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader()->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertSame( [ 'ext.neowiki' ], $this->addedModules );
	}

	public function testAddsCoreModuleStyles(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader()->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertSame( [ 'ext.neowiki.styles' ], $this->addedModuleStyles );
	}

	public function testAppendsModulesContributedByExtensions(): void {
		$this->setTemporaryHook(
			'NeoWikiGetFrontendModules',
			static function ( array &$modules ): void {
				$modules[] = 'ext.redherb-test';
			}
		);

		$this->newLoader()->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertSame( [ 'ext.neowiki', 'ext.redherb-test' ], $this->addedModules );
	}

	public function testPassesOutputAndSkinToHookHandlers(): void {
		$out = $this->newCapturingOutputPage();
		$skin = $this->createMock( Skin::class );

		$receivedOut = null;
		$receivedSkin = null;
		$this->setTemporaryHook(
			'NeoWikiGetFrontendModules',
			static function ( array &$modules, OutputPage $hookOut, Skin $hookSkin )
				use ( &$receivedOut, &$receivedSkin ): void {
				$receivedOut = $hookOut;
				$receivedSkin = $hookSkin;
			}
		);

		$this->newLoader()->load( $out, $skin );

		$this->assertSame( $out, $receivedOut );
		$this->assertSame( $skin, $receivedSkin );
	}

	public function testEmitsConfiguredValidationDebounceJsConfigVar(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader( 450 )->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertSame( 450, $this->addedJsConfigVars['wgNeoWikiValidationDebounceMs'] ?? null );
	}

	public function testEmitsWhetherValidationIsEnforcedAsJsConfigVar(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader( validationEnforced: true )->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertTrue( $this->addedJsConfigVars['wgNeoWikiEnforceValidation'] ?? null );
	}

	public function testEmitsFalseWhenValidationIsNotEnforced(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader( validationEnforced: false )->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertFalse( $this->addedJsConfigVars['wgNeoWikiEnforceValidation'] ?? null );
	}

	public function testEmitsThatTheWikiIsSubjectFirstAsJsConfigVar(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader( subjectFirst: true )->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertTrue( $this->addedJsConfigVars['wgNeoWikiSubjectFirst'] ?? null );
	}

	public function testEmitsFalseOnAPageFirstWiki(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader( subjectFirst: false )->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertFalse( $this->addedJsConfigVars['wgNeoWikiSubjectFirst'] ?? null );
	}

	public function testEmitsThatTheViewerMayEditThePagesSubjects(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader()->loadWithSubjectPermissions(
			$this->newCapturingOutputPage(),
			$this->createMock( Skin::class ),
			true
		);

		$this->assertTrue( $this->addedJsConfigVars['wgNeoWikiCanEditPageSubjects'] ?? null );
	}

	public function testEmitsThatTheViewerMayNotEditThePagesSubjects(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader()->loadWithSubjectPermissions(
			$this->newCapturingOutputPage(),
			$this->createMock( Skin::class ),
			false
		);

		$this->assertFalse( $this->addedJsConfigVars['wgNeoWikiCanEditPageSubjects'] ?? null );
	}

	public function testLoadsTheFrontendModuleAlongsideThePermission(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader()->loadWithSubjectPermissions(
			$this->newCapturingOutputPage(),
			$this->createMock( Skin::class ),
			true
		);

		$this->assertSame( [ 'ext.neowiki' ], $this->addedModules );
	}

	public function testLeavesTheDeniedReasonToPagesCarryingTheButton(): void {
		$this->clearHook( 'NeoWikiGetFrontendModules' );

		$this->newLoader()->load( $this->newCapturingOutputPage(), $this->createMock( Skin::class ) );

		$this->assertArrayNotHasKey( 'wgNeoWikiCreateSubjectPageDeniedReason', $this->addedJsConfigVars );
	}

	public function testTellsAViewerWhoMayNotEditWhoMayCreateSubjectPages(): void {
		$this->grantEditToAdministratorsOnly();

		$reason = $this->deniedReasonFor( $this->getMutableTestUser()->getUser() );

		$this->assertStringContainsString( 'Administrators', $reason ?? '' );
	}

	public function testNamesTheGroupsOfTheFirstRightTheViewerLacks(): void {
		$this->setGroupPermissions( [
			'*' => [ 'edit' => false, 'createpage' => false ],
			'user' => [ 'edit' => false, 'createpage' => false ],
			'sysop' => [ 'edit' => true ],
			'bureaucrat' => [ 'createpage' => true ],
		] );

		$reason = $this->deniedReasonFor( $this->getMutableTestUser()->getUser() );

		$this->assertStringContainsString( 'Administrators', $reason ?? '' );
		$this->assertStringNotContainsString( 'Bureaucrats', $reason ?? '' );
	}

	public function testStatesTheReasonAsPlainText(): void {
		$this->grantEditToAdministratorsOnly();

		$reason = $this->deniedReasonFor( $this->getMutableTestUser()->getUser() );

		$this->assertStringNotContainsString( '[[', $reason ?? '' );
		$this->assertStringNotContainsString( '<', $reason ?? '' );
	}

	public function testGivesNoReasonToAViewerWhoMayCreateSubjectPages(): void {
		$this->setGroupPermissions( [ '*' => [ 'edit' => false ], 'user' => [ 'edit' => true, 'createpage' => true ] ] );

		$this->assertNull( $this->deniedReasonFor( $this->getMutableTestUser()->getUser() ) );
	}

	public function testGivesNoReasonWhenTheAuthorityDoesNotSayWhy(): void {
		$silentlyDenying = new class( new UserIdentityValue( 0, 'Anonymous' ), [] ) extends SimpleAuthority {
			public function isAllowed( string $permission, ?PermissionStatus $status = null ): bool {
				return false;
			}
		};

		$this->assertNull( $this->deniedReasonFor( $silentlyDenying ) );
	}

	private function grantEditToAdministratorsOnly(): void {
		$this->setGroupPermissions( [
			'*' => [ 'edit' => false ],
			'user' => [ 'edit' => false ],
			'sysop' => [ 'edit' => true ],
		] );
	}

	private function deniedReasonFor( Authority $authority ): ?string {
		$context = new RequestContext();
		$context->setTitle( Title::makeTitle( NS_MAIN, 'Carries a button' ) );
		$context->setAuthority( $authority );
		$context->setLanguage( 'en' );

		$out = $context->getOutput();
		$this->newLoader()->loadCreateSubjectPageDeniedReason( $out );

		$this->assertArrayHasKey( 'wgNeoWikiCreateSubjectPageDeniedReason', $out->getJsConfigVars() );
		return $out->getJsConfigVars()['wgNeoWikiCreateSubjectPageDeniedReason'];
	}

	private function newLoader(
		int $validationDebounceMs = 300,
		bool $validationEnforced = false,
		bool $subjectFirst = false
	): FrontendModuleLoader {
		return new FrontendModuleLoader(
			$this->getServiceContainer()->getHookContainer(),
			$validationDebounceMs,
			$validationEnforced,
			$subjectFirst,
		);
	}

	private function newCapturingOutputPage(): OutputPage {
		$out = $this->createMock( OutputPage::class );
		$out->method( 'addModules' )->willReturnCallback(
			function ( string|array $modules ): void {
				$this->addedModules = array_merge( $this->addedModules, (array)$modules );
			}
		);
		$out->method( 'addModuleStyles' )->willReturnCallback(
			function ( string|array $modules ): void {
				$this->addedModuleStyles = array_merge( $this->addedModuleStyles, (array)$modules );
			}
		);
		$out->method( 'addJsConfigVars' )->willReturnCallback(
			function ( $keys, $value = null ): void {
				if ( is_array( $keys ) ) {
					$this->addedJsConfigVars = array_merge( $this->addedJsConfigVars, $keys );
				} else {
					$this->addedJsConfigVars[ $keys ] = $value;
				}
			}
		);
		return $out;
	}

}
