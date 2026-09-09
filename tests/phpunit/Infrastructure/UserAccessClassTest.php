<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Infrastructure;

use MediaWiki\User\User;
use MediaWikiIntegrationTestCase;
use ProfessionalWiki\NeoWiki\Infrastructure\UserAccessClass;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\Infrastructure\UserAccessClass
 * @group Database
 */
class UserAccessClassTest extends MediaWikiIntegrationTestCase {

	private function newAccessClass(): UserAccessClass {
		return NeoWikiExtension::getInstance()->newUserAccessClass();
	}

	public function testAnonymousAndLoggedInReadersAreDifferentClasses(): void {
		$accessClass = $this->newAccessClass();

		$this->assertNotSame(
			$accessClass->of( $this->getServiceContainer()->getUserFactory()->newAnonymous() ),
			$accessClass->of( $this->getTestUser()->getUser() )
		);
	}

	/**
	 * Group names reach the class from hooks and the database, so a group whose name holds a separator
	 * must not be able to describe like a different set of groups.
	 */
	public function testAGroupNamedAfterOtherGroupsDoesNotShareTheirClass(): void {
		$impostor = $this->getMutableTestUser( [ 'sysop,user' ] )->getUser();

		$accessClass = $this->newAccessClass();

		$this->assertNotSame(
			$accessClass->of( $this->getTestSysop()->getUser() ),
			$accessClass->of( $impostor )
		);
	}

	public function testUsersWithTheSameGroupsShareAClass(): void {
		$accessClass = $this->newAccessClass();

		$this->assertSame(
			$accessClass->of( $this->getTestUser( [ 'bot' ] )->getUser() ),
			$accessClass->of( $this->getMutableTestUser( [ 'bot' ] )->getUser() )
		);
	}

	public function testUsersWithDifferentGroupsHaveDifferentClasses(): void {
		$accessClass = $this->newAccessClass();

		$this->assertNotSame(
			$accessClass->of( $this->getTestUser()->getUser() ),
			$accessClass->of( $this->getTestSysop()->getUser() )
		);
	}

	/**
	 * Installed before the first class is computed: the permission manager memoizes rights per user.
	 */
	private function grantRightOutsideGroups( User $grantee, string $right ): void {
		$this->setTemporaryHook(
			'UserGetRights',
			static function ( User $user, array &$rights ) use ( $grantee, $right ): void {
				if ( $user->equals( $grantee ) ) {
					$rights[] = $right;
				}
			}
		);
	}

	public function testTheQueryRightSeparatesUsersInTheSameGroups(): void {
		$this->setGroupPermissions( '*', 'neowiki-query', false );
		$granted = $this->getTestUser()->getUser();
		$other = $this->getMutableTestUser()->getUser();
		$this->grantRightOutsideGroups( $granted, 'neowiki-query' );

		$accessClass = $this->newAccessClass();

		$this->assertNotSame( $accessClass->of( $granted ), $accessClass->of( $other ) );
	}

	public function testTheReadRightSeparatesUsersInTheSameGroups(): void {
		$this->setGroupPermissions( '*', 'read', false );
		$this->setGroupPermissions( 'user', 'read', false );
		$granted = $this->getTestUser()->getUser();
		$other = $this->getMutableTestUser()->getUser();
		$this->grantRightOutsideGroups( $granted, 'read' );

		$accessClass = $this->newAccessClass();

		$this->assertNotSame( $accessClass->of( $granted ), $accessClass->of( $other ) );
	}

}
