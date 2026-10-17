<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseReferencingSubjectLookup;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\NeoWikiIntegrationTestCase;

/**
 * Needs the integration base class for the pages whose saves fill the tables the lookup reads.
 *
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\DatabaseReferencingSubjectLookup
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LowestPageRows
 * @group Database
 */
class DatabaseReferencingSubjectLookupTest extends NeoWikiIntegrationTestCase {

	private const string TARGET_ID = 'sTestDRS1111111';
	private const string SOURCE_ID = 'sTestDRS1111112';
	private const string OTHER_SOURCE_ID = 'sTestDRS1111113';
	private const string THIRD_SOURCE_ID = 'sTestDRS1111114';
	private const string FOURTH_SOURCE_ID = 'sTestDRS1111115';

	public function testIgnoresASubjectTheTargetPointsAtWithoutPointingBack(): void {
		$this->createPageWithoutMainSubject(
			'Referrers',
			$this->referrer( self::TARGET_ID, 'Target', targetId: self::SOURCE_ID ),
			$this->referrer( self::SOURCE_ID, 'Neighbour', targetId: self::OTHER_SOURCE_ID )
		);

		$this->assertSame( [], $this->idsOfSubjectsReferencingTarget() );
	}

	public function testNeverNamesTheTargetItself(): void {
		$this->createPageWithoutMainSubject( 'Referrers', $this->referrer( self::TARGET_ID, 'Target', targetId: self::TARGET_ID ) );

		$this->assertSame( [], $this->idsOfSubjectsReferencingTarget() );
	}

	public function testNamesASubjectOnceHoweverManyRelationsItHasIntoTheTarget(): void {
		$this->createPageWithoutMainSubject(
			'Referrers',
			$this->target(),
			TestSubject::build(
				id: self::SOURCE_ID,
				label: 'Referring subject',
				statements: new StatementList( [
					TestStatement::buildRelation( 'Made in', [ TestRelation::build( targetId: self::TARGET_ID ) ] ),
					TestStatement::buildRelation( 'Sold in', [ TestRelation::build( targetId: self::TARGET_ID ) ] ),
				] )
			)
		);

		$this->assertSame( [ self::SOURCE_ID ], $this->idsOfSubjectsReferencingTarget() );
	}

	/**
	 * Under a name of its own on each page, so that the copies differ in what the order reads.
	 */
	public function testNamesASubjectThatSeveralPagesHoldOnce(): void {
		$this->createPageWithoutMainSubject( 'Referrers', $this->target(), $this->referrer( self::SOURCE_ID, 'Referring subject' ) );
		$this->createPageWithoutMainSubject( 'Copy', $this->referrer( self::SOURCE_ID, 'Copied referring subject' ) );

		$this->assertSame( [ self::SOURCE_ID ], $this->idsOfSubjectsReferencingTarget() );
	}

	/**
	 * In byte order the capitalized names would come before "anvil". The namesake with the lower id points in through
	 * the later property, so their order cannot come from how the relations are stored.
	 */
	public function testOrdersByNameIgnoringCaseThenById(): void {
		$this->createPageWithoutMainSubject(
			'Referrers',
			$this->target(),
			$this->referrer( self::SOURCE_ID, 'Zeppelin' ),
			$this->referrer( self::FOURTH_SOURCE_ID, 'Bellows' ),
			$this->referrer( self::OTHER_SOURCE_ID, 'anvil' ),
			$this->referrer( self::THIRD_SOURCE_ID, 'Bellows', property: 'Owned by' ),
		);

		$this->assertSame(
			[ self::OTHER_SOURCE_ID, self::THIRD_SOURCE_ID, self::FOURTH_SOURCE_ID, self::SOURCE_ID ],
			$this->idsOfSubjectsReferencingTarget()
		);
	}

	public function testOrdersSubjectsWithoutANameAfterNamedOnesBySchemaName(): void {
		$this->createPageWithoutMainSubject(
			'Referrers',
			$this->target(),
			$this->referrer( self::SOURCE_ID, null, schema: 'Bridge' ),
			$this->referrer( self::OTHER_SOURCE_ID, 'Zeppelin' ),
			$this->referrer( self::THIRD_SOURCE_ID, null, schema: 'Anvil' ),
		);

		$this->assertSame(
			[ self::OTHER_SOURCE_ID, self::THIRD_SOURCE_ID, self::SOURCE_ID ],
			$this->idsOfSubjectsReferencingTarget()
		);
	}

	public function testStopsAtTheLimit(): void {
		$this->createPageWithoutMainSubject(
			'Referrers',
			$this->target(),
			$this->referrer( self::SOURCE_ID, 'Anvil' ),
			$this->referrer( self::OTHER_SOURCE_ID, 'Zeppelin' )
		);

		$this->assertSame( [ self::SOURCE_ID ], $this->idsOfSubjectsReferencingTarget( limit: 1 ) );
	}

	private function target(): Subject {
		return TestSubject::build( id: self::TARGET_ID, label: 'Target' );
	}

	private function referrer(
		string $id,
		?string $label,
		string $targetId = self::TARGET_ID,
		string $schema = TestSubject::DEFAULT_SCHEMA_ID,
		string $property = 'Made in'
	): Subject {
		return TestSubject::build(
			id: $id,
			label: $label,
			schemaName: new SchemaName( $schema ),
			statements: new StatementList( [
				TestStatement::buildRelation( $property, [ TestRelation::build( targetId: $targetId ) ] ),
			] )
		);
	}

	/**
	 * @return string[]
	 */
	private function idsOfSubjectsReferencingTarget( int $limit = 10 ): array {
		return array_map(
			static fn ( SubjectId $id ): string => $id->text,
			( new DatabaseReferencingSubjectLookup( $this->getServiceContainer()->getConnectionProvider() ) )
				->getIdsOfSubjectsReferencing( new SubjectId( self::TARGET_ID ), $limit )
		);
	}

}
