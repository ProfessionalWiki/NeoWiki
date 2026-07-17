<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWiki\User\UserIdentityValue;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedPageReadAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;
use Psr\Log\NullLogger;
use TestLogger;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedPageReadAuthorizer
 */
class AuthorityBasedPageReadAuthorizerTest extends MediaWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

	private const int PAGE_ID = 42;

	public function testReadByPageIdIsDeniedWhenThePageCannotBeRead(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithGlobalReadButNoPageRead() );

		$this->assertFalse( $authorizer->authorizeReadByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testReadByPageIdIsAllowedWhenThePageCanBeRead(): void {
		// Also asserts logger silence, so a future edit that moves the logger->info call outside
		// the denial branch fails this test.
		$logger = new TestLogger( true );
		$authorizer = new AuthorityBasedPageReadAuthorizer(
			$this->authorityThatAllowsEverything(),
			$this->titleFactoryReturningPage(),
			$logger
		);

		$this->assertTrue( $authorizer->authorizeReadByPageId( new PageId( self::PAGE_ID ) ) );
		$this->assertSame( [], $logger->getBuffer() );
	}

	public function testReadByPageIdDeniesWhenThePageCannotBeResolved(): void {
		// Unlike the write side there is no global-right fallback: content is only reachable
		// through a resolved page, so an unresolvable one has nothing to authorize.
		$authorizer = new AuthorityBasedPageReadAuthorizer(
			$this->authorityThatAllowsEverything(),
			$this->titleFactoryReturningNull(),
			new NullLogger()
		);

		$this->assertFalse( $authorizer->authorizeReadByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testReadByPageTitleIsDeniedWhenThePageCannotBeRead(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithGlobalReadButNoPageRead() );

		$this->assertFalse(
			$authorizer->authorizeReadByPageTitle( Title::makeTitle( NS_MAIN, 'Protected page' ) )
		);
	}

	public function testReadByPageTitleIsAllowedWhenThePageCanBeRead(): void {
		$authorizer = $this->newAuthorizer( $this->authorityThatAllowsEverything() );

		$this->assertTrue(
			$authorizer->authorizeReadByPageTitle( Title::makeTitle( NS_MAIN, 'Public page' ) )
		);
	}

	public function testReadByPageTitleAuthorizesTheGivenTitleRatherThanResolvingTheIdAgain(): void {
		// The title-keyed entry point exists so name-keyed callers do not pay a second page
		// lookup. A TitleFactory that would resolve a different page proves it is not consulted.
		$titleFactory = $this->createStub( TitleFactory::class );
		$titleFactory->method( 'newFromID' )->willReturn( Title::makeTitle( NS_MAIN, 'Some other page' ) );

		$authority = $this->createMock( Authority::class );
		$authority->method( 'authorizeRead' )->willReturnCallback(
			static fn ( string $action, PageIdentity $page ): bool => $page->getDBkey() === 'Wanted_page'
		);

		$authorizer = new AuthorityBasedPageReadAuthorizer( $authority, $titleFactory, new NullLogger() );

		$this->assertTrue(
			$authorizer->authorizeReadByPageTitle( Title::makeTitle( NS_MAIN, 'Wanted page' ) )
		);
	}

	public function testGateUsesBindingAuthorizeRead(): void {
		// Pin the verb: authorizeRead runs the full per-title check including the expensive ACL
		// hook that quick checks skip. Reverting to a hint verb fails this test.
		$authority = $this->createMock( Authority::class );
		$authority->method( 'probablyCan' )->willReturn( true );
		$authority->method( 'definitelyCan' )->willReturn( true );
		$authority->method( 'authorizeRead' )->willReturn( false );
		$authority->method( 'getUser' )->willReturn( new UserIdentityValue( 9999, 'Petr' ) );

		$authorizer = new AuthorityBasedPageReadAuthorizer(
			$authority,
			$this->titleFactoryReturningPage(),
			new NullLogger()
		);

		$this->assertFalse( $authorizer->authorizeReadByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testGateChecksTheReadAction(): void {
		// An authority that permits only the 'read' action: a gate asking for any other
		// action (e.g. 'edit') is denied, pinning the permission string end-to-end.
		$authority = $this->createMock( Authority::class );
		$authority->method( 'authorizeRead' )->willReturnCallback(
			static fn ( string $action ): bool => $action === 'read'
		);
		$authority->method( 'getUser' )->willReturn( new UserIdentityValue( 9999, 'Petr' ) );

		$authorizer = new AuthorityBasedPageReadAuthorizer(
			$authority,
			$this->titleFactoryReturningPage(),
			new NullLogger()
		);

		$this->assertTrue( $authorizer->authorizeReadByPageId( new PageId( self::PAGE_ID ) ) );
	}

	public function testDeniedReadIsLogged(): void {
		$logger = new TestLogger( true, null, true );
		$authorizer = new AuthorityBasedPageReadAuthorizer(
			$this->authorityWithoutAnyPermissions(),
			$this->titleFactoryReturningPage(),
			$logger
		);

		$authorizer->authorizeReadByPageId( new PageId( self::PAGE_ID ) );

		$this->assertSame(
			[ [ 'info', 'Denied read of page {page} to {user}',
				[ 'page' => 'Protected_page', 'user' => 'Petr' ] ] ],
			$logger->getBuffer()
		);
	}

	private function newAuthorizer( Authority $authority ): AuthorityBasedPageReadAuthorizer {
		return new AuthorityBasedPageReadAuthorizer(
			$authority,
			$this->titleFactoryReturningPage(),
			new NullLogger()
		);
	}

	private function authorityThatAllowsEverything(): Authority {
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
