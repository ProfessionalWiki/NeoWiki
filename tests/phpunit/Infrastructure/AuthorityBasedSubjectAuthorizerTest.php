<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedSubjectAuthorizer;
use Psr\Log\NullLogger;
use TestLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedSubjectAuthorizer
 */
class AuthorityBasedSubjectAuthorizerTest extends MediaWikiIntegrationTestCase {

	use MockAuthorityTrait;

	private const int PAGE_ID = 42;

	public function testCanCreateMainSubjectIsDeniedWhenThePageCannotBeEdited(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithGlobalEditButNoPageEdit() );

		$this->assertFalse( $authorizer->canCreateMainSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testCanCreateChildSubjectIsDeniedWhenThePageCannotBeEdited(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithGlobalEditButNoPageEdit() );

		$this->assertFalse( $authorizer->canCreateChildSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testCanEditSubjectIsDeniedWhenThePageCannotBeEdited(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithGlobalEditButNoPageEdit() );

		$this->assertFalse( $authorizer->canEditSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testEditIsAllowedWhenThePageCanBeEdited(): void {
		$authorizer = $this->newAuthorizer( $this->authorityThatCanEditEveryPage() );

		$this->assertTrue( $authorizer->canEditSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testFallsBackToGlobalEditRightWhenThePageCannotBeResolved(): void {
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityThatCanEditEveryPage(),
			$this->titleFactoryReturningNull(),
			new NullLogger()
		);

		$this->assertTrue( $authorizer->canEditSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testDeniesUnresolvablePageWhenUserLacksGlobalEditRight(): void {
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityWithoutAnyPermissions(),
			$this->titleFactoryReturningNull(),
			new NullLogger()
		);

		$this->assertFalse( $authorizer->canEditSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testFallsBackToGlobalEditRightWhenNoPageIsGiven(): void {
		$authorizer = $this->newAuthorizer( $this->authorityThatCanEditEveryPage() );

		$this->assertTrue( $authorizer->canEditSubject( null ) );
	}

	public function testDeniesWhenNoPageIsGivenAndUserLacksGlobalEditRight(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithoutAnyPermissions() );

		$this->assertFalse( $authorizer->canEditSubject( null ) );
	}

	public function testAuthorizeIsDeniedWhenThePageCannotBeEdited(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithGlobalEditButNoPageEdit() );

		$this->assertFalse( $authorizer->authorize( new PageId( self::PAGE_ID ) ) );
	}

	public function testAuthorizeFallsBackToGlobalEditRightWhenThePageCannotBeResolved(): void {
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityThatCanEditEveryPage(),
			$this->titleFactoryReturningNull(),
			new NullLogger()
		);

		$this->assertTrue( $authorizer->authorize( new PageId( self::PAGE_ID ) ) );
	}

	public function testAuthorizeDeniesUnresolvablePageWhenUserLacksGlobalEditRight(): void {
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityWithoutAnyPermissions(),
			$this->titleFactoryReturningNull(),
			new NullLogger()
		);

		$this->assertFalse( $authorizer->authorize( new PageId( self::PAGE_ID ) ) );
	}

	public function testAuthorizeUsesAuthorizeWriteWhileHintsUseDefinitelyCan(): void {
		// authorizeWrite (used for the real write) enforces RIGOR_SECURE + the edit rate limit;
		// definitelyCan (used for hints) does neither. Assert each path delegates to the right one.
		$title = Title::makeTitle( NS_MAIN, 'Target page' );
		$titleFactory = $this->createStub( TitleFactory::class );
		$titleFactory->method( 'newFromID' )->willReturn( $title );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'authorizeWrite' )->willReturn( false );
		$authority->method( 'definitelyCan' )->willReturn( true );

		$authorizer = new AuthorityBasedSubjectAuthorizer( $authority, $titleFactory, new NullLogger() );
		$pageId = new PageId( self::PAGE_ID );

		$this->assertFalse( $authorizer->authorize( $pageId ) );
		$this->assertTrue( $authorizer->canEditSubject( $pageId ) );
	}

	public function testAuthorizeReadIsDeniedWhenThePageCannotBeRead(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithGlobalReadButNoPageRead() );

		$this->assertFalse( $authorizer->authorizeRead( new PageId( self::PAGE_ID ) ) );
	}

	public function testAuthorizeReadIsAllowedWhenThePageCanBeRead(): void {
		// Also asserts logger silence, so a future edit that moves the logger->info call outside
		// the denial branch fails this test.
		$logger = new TestLogger( true );
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityThatCanEditEveryPage(),
			$this->titleFactoryReturningPage(),
			$logger
		);

		$this->assertTrue( $authorizer->authorizeRead( new PageId( self::PAGE_ID ) ) );
		$this->assertSame( [], $logger->getBuffer() );
	}

	public function testAuthorizeReadDeniesWhenThePageCannotBeResolved(): void {
		// Unlike the write side there is no global-right fallback: content is only reachable
		// through a resolved page, so an unresolvable one has nothing to authorize.
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityThatCanEditEveryPage(),
			$this->titleFactoryReturningNull(),
			new NullLogger()
		);

		$this->assertFalse( $authorizer->authorizeRead( new PageId( self::PAGE_ID ) ) );
	}

	public function testAuthorizeReadUsesBindingAuthorizeRead(): void {
		// Pin the verb: authorizeRead runs the full per-title check including the expensive ACL
		// hook that quick checks skip. Reverting to a hint verb fails this test.
		$title = Title::makeTitle( NS_MAIN, 'Target page' );
		$titleFactory = $this->createStub( TitleFactory::class );
		$titleFactory->method( 'newFromID' )->willReturn( $title );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'probablyCan' )->willReturn( true );
		$authority->method( 'definitelyCan' )->willReturn( true );
		$authority->method( 'authorizeRead' )->willReturn( false );
		$authority->method( 'getUser' )->willReturn( new UserIdentityValue( 9999, 'Petr' ) );

		$authorizer = new AuthorityBasedSubjectAuthorizer( $authority, $titleFactory, new NullLogger() );

		$this->assertFalse( $authorizer->authorizeRead( new PageId( self::PAGE_ID ) ) );
	}

	public function testDeniedReadIsLogged(): void {
		$logger = new TestLogger( true, null, true );
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityWithoutAnyPermissions(),
			$this->titleFactoryReturningPage(),
			$logger
		);

		$authorizer->authorizeRead( new PageId( self::PAGE_ID ) );

		$this->assertSame(
			[ [ 'info', 'NeoWiki: denied read of page {page} to {user}',
				[ 'page' => 'Protected_page', 'user' => 'Petr' ] ] ],
			$logger->getBuffer()
		);
	}

	private function newAuthorizer( Authority $authority ): AuthorityBasedSubjectAuthorizer {
		return new AuthorityBasedSubjectAuthorizer( $authority, $this->titleFactoryReturningPage(), new NullLogger() );
	}

	/**
	 * Holds the wiki-global 'edit' right, but cannot edit any specific page
	 * (as when the page is protected or in a restricted namespace).
	 */
	private function authorityWithGlobalEditButNoPageEdit(): Authority {
		$canEditGloballyButNotPerPage = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$permission === 'edit' && $page === null;

		return $this->mockRegisteredAuthority( $canEditGloballyButNotPerPage );
	}

	/**
	 * Holds the wiki-global 'read' right, but cannot read any specific page
	 * (as under a restricted namespace, $wgWhitelistRead, or an ACL extension).
	 */
	private function authorityWithGlobalReadButNoPageRead(): Authority {
		$canReadGloballyButNotPerPage = static fn ( string $permission, ?PageIdentity $page = null ): bool =>
			$permission === 'read' && $page === null;

		return $this->mockRegisteredAuthority( $canReadGloballyButNotPerPage );
	}

	private function authorityThatCanEditEveryPage(): Authority {
		$allowEverything = static fn ( string $permission, ?PageIdentity $page = null ): bool => true;

		return $this->mockRegisteredAuthority( $allowEverything );
	}

	private function authorityWithoutAnyPermissions(): Authority {
		$denyEverything = static fn ( string $permission, ?PageIdentity $page = null ): bool => false;

		return $this->mockRegisteredAuthority( $denyEverything );
	}

	private function titleFactoryReturningPage(): TitleFactory {
		$factory = $this->createStub( TitleFactory::class );
		$factory->method( 'newFromID' )->willReturn( Title::makeTitle( NS_MAIN, 'Protected page' ) );
		return $factory;
	}

	private function titleFactoryReturningNull(): TitleFactory {
		$factory = $this->createStub( TitleFactory::class );
		$factory->method( 'newFromID' )->willReturn( null );
		return $factory;
	}

}
