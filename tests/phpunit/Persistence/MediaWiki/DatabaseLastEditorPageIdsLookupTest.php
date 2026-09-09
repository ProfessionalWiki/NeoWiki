<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use MediaWiki\User\User;
use MediaWiki\User\UserIdentity;
use MediaWiki\User\UserIdentityValue;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseLastEditorPageIdsLookup;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseLastEditorPageIdsLookup
 * @group Database
 */
class DatabaseLastEditorPageIdsLookupTest extends NeoWikiIntegrationTestCase {

	public function testFindsThePagesTheUserEditedLast(): void {
		$author = $this->getTestUser()->getUser();

		$first = $this->editPageAs( 'Their first page', $author );
		$second = $this->editPageAs( 'Their second page', $author );

		$this->assertSame( [ $first, $second ], $this->getPageIdsLastEditedBy( $author ) );
	}

	/**
	 * A page is projected from its current revision alone, so a page the user edited and someone else
	 * edited afterwards no longer carries their name and must not be reprojected on their account.
	 */
	public function testSkipsAPageSomeoneElseEditedAfterwards(): void {
		$author = $this->getTestUser()->getUser();

		$stillTheirs = $this->editPageAs( 'Page still theirs', $author );
		$this->editPageAs( 'Page taken over', $author );
		$this->editPageAs( 'Page taken over', $this->getTestSysop()->getUser() );

		$this->assertSame( [ $stillTheirs ], $this->getPageIdsLastEditedBy( $author ) );
	}

	public function testFindsNothingForAUserWhoNeverEdited(): void {
		$this->editPageAs( 'A page by someone else', $this->getTestSysop()->getUser() );

		$this->assertSame( [], $this->getPageIdsLastEditedBy( $this->getTestUser()->getUser() ) );
	}

	/**
	 * findActorId answers null for a user who has never acted, which has to read as "no pages" rather
	 * than reach the query with no actor to narrow by.
	 */
	public function testFindsNothingForAUserWithoutAnActor(): void {
		$this->assertSame(
			[],
			$this->getPageIdsLastEditedBy( UserIdentityValue::newRegistered( 123456, 'Never acted here' ) )
		);
	}

	private function editPageAs( string $pageName, User $author ): int {
		return $this->editPage( $pageName, 'Edited by ' . $author->getName(), '', NS_MAIN, $author )
			->getNewRevision()
			->getPageId();
	}

	/**
	 * @return int[]
	 */
	private function getPageIdsLastEditedBy( UserIdentity $user ): array {
		$services = $this->getServiceContainer();

		return ( new DatabaseLastEditorPageIdsLookup(
			$services->getConnectionProvider()->getReplicaDatabase(),
			$services->getActorNormalization()
		) )->getPageIdsLastEditedBy( $user );
	}

}
