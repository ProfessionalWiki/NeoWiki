<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIdsLookup;
use ProfessionalWiki\NeoWiki\Persistence\SubjectPageIdsLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseSubjectPageIdsLookup
 * @group Database
 */
class DatabaseSubjectPageIdsLookupTest extends NeoWikiIntegrationTestCase {

	protected function setUp(): void {
		parent::setUp();
		$this->setUpNeo4j();
		$this->createSchema( TestSubject::DEFAULT_SCHEMA_ID );
	}

	public function testPagesCarryingASubjectAreReturnedInPageIdOrder(): void {
		$pageIds = $this->createSubjectPages( 'First', 'Second', 'Third' );

		$this->assertSame( $pageIds, $this->newLookup()->getSubjectPageIdsAfter( 0, 100 ) );
	}

	public function testAPageWithoutASubjectIsNotReturned(): void {
		$subjectPageId = $this->createSubjectPages( 'Has a subject' )[0];
		$this->editPage( 'Has no subject', 'plain wikitext' );

		$this->assertSame( [ $subjectPageId ], $this->newLookup()->getSubjectPageIdsAfter( 0, 100 ) );
	}

	public function testOnlyThePagesAfterTheCursorAreReturned(): void {
		$pageIds = $this->createSubjectPages( 'First', 'Second', 'Third' );

		$this->assertSame(
			[ $pageIds[2] ],
			$this->newLookup()->getSubjectPageIdsAfter( $pageIds[1], 100 )
		);
	}

	public function testNoMorePagesThanTheLimitAreReturned(): void {
		$pageIds = $this->createSubjectPages( 'First', 'Second', 'Third' );

		$this->assertSame( [ $pageIds[0], $pageIds[1] ], $this->newLookup()->getSubjectPageIdsAfter( 0, 2 ) );
	}

	public function testTheCursorPastTheLastPageReturnsNothing(): void {
		$pageIds = $this->createSubjectPages( 'First', 'Second' );

		$this->assertSame( [], $this->newLookup()->getSubjectPageIdsAfter( $pageIds[1], 100 ) );
	}

	public function testTheSubjectPagesAreCounted(): void {
		$this->createSubjectPages( 'First', 'Second', 'Third' );
		$this->editPage( 'Has no subject', 'plain wikitext' );

		$this->assertSame( 3, $this->newLookup()->countSubjectPages() );
	}

	public function testAWikiThatHasNeverStoredASubjectHasNoSubjectPages(): void {
		// Without the 'neowiki-subjects' slot role there is no role id to look up, which must read as an
		// empty wiki rather than crash the rebuild that asks.
		$this->truncateTable( 'slot_roles' );
		$this->getServiceContainer()->resetServiceForTesting( 'SlotRoleStore' );

		$lookup = $this->newLookup();

		$this->assertSame( [], $lookup->getSubjectPageIdsAfter( 0, 100 ) );
		$this->assertSame( 0, $lookup->countSubjectPages() );
	}

	private function newLookup(): SubjectPageIdsLookup {
		return new DatabaseSubjectPageIdsLookup(
			$this->getServiceContainer()->getConnectionProvider()->getReplicaDatabase(),
			$this->getServiceContainer()->getSlotRoleStore()
		);
	}

}
