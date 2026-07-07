<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\Page\PageIdentity;
use MediaWiki\Permissions\Authority;
use MediaWiki\Tests\Unit\Permissions\MockAuthorityTrait;
use MediaWiki\Title\Title;
use MediaWiki\Title\TitleFactory;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Domain\Page\PageId;
use ProfessionalWiki\NeoWiki\Infrastructure\AuthorityBasedSubjectAuthorizer;

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

	public function testCanDeleteSubjectIsDeniedWhenThePageCannotBeEdited(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithGlobalEditButNoPageEdit() );

		$this->assertFalse( $authorizer->canDeleteSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testEditIsAllowedWhenThePageCanBeEdited(): void {
		$authorizer = $this->newAuthorizer( $this->authorityThatCanEditEveryPage() );

		$this->assertTrue( $authorizer->canEditSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testFallsBackToGlobalEditRightWhenThePageCannotBeResolved(): void {
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityThatCanEditEveryPage(),
			$this->titleFactoryReturningNull()
		);

		$this->assertTrue( $authorizer->canEditSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testDeniesUnresolvablePageWhenUserLacksGlobalEditRight(): void {
		$authorizer = new AuthorityBasedSubjectAuthorizer(
			$this->authorityWithoutAnyPermissions(),
			$this->titleFactoryReturningNull()
		);

		$this->assertFalse( $authorizer->canEditSubject( new PageId( self::PAGE_ID ) ) );
	}

	public function testFallsBackToGlobalEditRightWhenNoPageIsGiven(): void {
		$authorizer = $this->newAuthorizer( $this->authorityThatCanEditEveryPage() );

		$this->assertTrue( $authorizer->canDeleteSubject( null ) );
	}

	public function testDeniesWhenNoPageIsGivenAndUserLacksGlobalEditRight(): void {
		$authorizer = $this->newAuthorizer( $this->authorityWithoutAnyPermissions() );

		$this->assertFalse( $authorizer->canDeleteSubject( null ) );
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
