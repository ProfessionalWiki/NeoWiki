<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedSubjectAuthorizer;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiMockAuthorityTrait;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedSubjectAuthorizer
 */
class AuthorityBasedSubjectAuthorizerTest extends MediaWikiIntegrationTestCase {

	use NeoWikiMockAuthorityTrait;

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

	/**
	 * A Subject on no page this wiki has offers no page rights to check, so it is refused however much
	 * the caller may edit elsewhere.
	 */
	public function testDeniesUnresolvablePageEvenWithTheGlobalEditRight(): void {
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityThatCanEditEveryPage(),
			$this->titleFactoryReturningNull()
		);

		$this->assertFalse( $authorizer->canEditSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testAuthorizeIsDeniedWhenThePageCannotBeEdited(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithGlobalEditButNoPageEdit() );

		$this->assertFalse( $authorizer->authorize( new PageId( self::PAGE_ID ) ) );
	}

	public function testAuthorizeDeniesUnresolvablePageEvenWithTheGlobalEditRight(): void {
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityThatCanEditEveryPage(),
			$this->titleFactoryReturningNull()
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

		$authorizer = new AuthorityBasedSubjectAuthorizer( $authority, $titleFactory );
		$pageId = new PageId( self::PAGE_ID );

		$this->assertFalse( $authorizer->authorize( $pageId ) );
		$this->assertTrue( $authorizer->canEditSubject( $pageId ) );
	}

	private function newAuthorizer( Authority $authority ): AuthorityBasedSubjectAuthorizer {
		return new AuthorityBasedSubjectAuthorizer( $authority, $this->titleFactoryReturningPage() );
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

	private function authorityThatCanEditEveryPage(): Authority {
		$allowEverything = static fn ( string $permission, ?PageIdentity $page = null ): bool => true;

		return $this->mockRegisteredAuthority( $allowEverything );
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
