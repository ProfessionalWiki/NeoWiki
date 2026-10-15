<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\HostSubject;
use ProfessionalWiki\NeoWiki\Application\HostSubjectBuilder;
use ProfessionalWiki\NeoWiki\Domain\Page\PageSubjects;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Tests\Data\TestNaming;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\HostSubjectBuilder
 * @covers \ProfessionalWiki\NeoWiki\Application\HostSubject
 */
class HostSubjectBuilderTest extends TestCase {

	private const string PERSON = 'sPerson11111111';
	private const string BIRTH = 'sBirth111111111';
	private const string RECORD = 'sRecord11111111';
	private const string PLACE = 'sSpain111111111';
	private const string PAGE_NAME = 'Pablo Picasso';

	private function newBuilder(): HostSubjectBuilder {
		return TestNaming::newHostSubjectBuilder(
			new InMemorySchemaLookup(
				TestSchema::build( name: 'Person' ),
				TestSchema::build( name: 'Birth', subjectsAreDependent: true ),
				TestSchema::build( name: 'Record', subjectsAreDependent: true ),
				TestSchema::build( name: 'Place' ),
			)
		);
	}

	public function testADependentSubjectIsNamedAfterTheSubjectThatPointsAtIt(): void {
		$birth = $this->subject( self::BIRTH, 'Birth' );

		$this->assertEquals(
			new HostSubject(
				id: self::PERSON,
				schema: 'Person',
				displayName: 'Pablo Picasso',
				displayNameIsGenerated: false,
				hostSubject: null
			),
			$this->newBuilder()->build( $birth, $this->picassoWith( $birth ), self::PAGE_NAME )
		);
	}

	public function testAHostSubjectNobodyNamedIsNamedAfterItsOwnHostSubject(): void {
		$record = $this->subject( self::RECORD, 'Record' );
		$page = $this->picassoWith(
			$this->subject( self::BIRTH, 'Birth', $this->relation( 'Record', self::RECORD ) ),
			$record
		);

		$this->assertEquals(
			new HostSubject(
				id: self::BIRTH,
				schema: 'Birth',
				displayName: 'Birth',
				displayNameIsGenerated: true,
				hostSubject: new HostSubject(
					id: self::PERSON,
					schema: 'Person',
					displayName: 'Pablo Picasso',
					displayNameIsGenerated: false,
					hostSubject: null
				)
			),
			$this->newBuilder()->build( $record, $page, self::PAGE_NAME )
		);
	}

	public function testAHostSubjectThatIsThePagesMainSubjectWithoutALabelIsNamedAfterThePage(): void {
		$birth = $this->subject( self::BIRTH, 'Birth' );
		$page = new PageSubjects(
			TestSubject::build(
				id: self::PERSON,
				label: null,
				schemaName: TestSchema::reference( 'Person' ),
				statements: new StatementList( [ $this->relation( 'Birth', self::BIRTH ) ] )
			),
			new SubjectMap( $birth )
		);

		$this->assertEquals(
			new HostSubject(
				id: self::PERSON,
				schema: 'Person',
				displayName: self::PAGE_NAME,
				displayNameIsGenerated: false,
				hostSubject: null
			),
			$this->newBuilder()->build( $birth, $page, self::PAGE_NAME )
		);
	}

	public function testAStandaloneSubjectIsNotNamedAfterTheSubjectThatPointsAtIt(): void {
		$place = $this->subject( self::PLACE, 'Place' );
		$page = $this->picassoWith(
			$this->subject( self::BIRTH, 'Birth', $this->relation( 'Place', self::PLACE ) ),
			$place
		);

		$this->assertNull( $this->newBuilder()->build( $place, $page, self::PAGE_NAME ) );
	}

	public function testHostSubjectsThatPointAtEachOtherAreNamedOnce(): void {
		$record = $this->subject( self::RECORD, 'Record', $this->relation( 'Birth', self::BIRTH ) );
		$page = new PageSubjects(
			null,
			new SubjectMap( $this->subject( self::BIRTH, 'Birth', $this->relation( 'Record', self::RECORD ) ), $record )
		);

		$this->assertEquals(
			new HostSubject(
				id: self::BIRTH,
				schema: 'Birth',
				displayName: 'Birth',
				displayNameIsGenerated: true,
				hostSubject: null
			),
			$this->newBuilder()->build( $record, $page, self::PAGE_NAME )
		);
	}

	public function testAChainOfHostSubjectsLongerThanTheBoundIsNamedUpToTheBound(): void {
		$records = $this->chainOfRecords( HostSubjectBuilder::MAX_NAMED_HOST_SUBJECTS + 2 );

		$this->assertSame(
			HostSubjectBuilder::MAX_NAMED_HOST_SUBJECTS,
			$this->lengthOf( $this->newBuilder()->build( $records[0], new PageSubjects( null, new SubjectMap( ...$records ) ), self::PAGE_NAME ) )
		);
	}

	/**
	 * @return Subject[] Records each pointed at by the next.
	 */
	private function chainOfRecords( int $length ): array {
		$ids = array_map( static fn ( int $index ): string => 'sRecordChainAA' . 'ABCDEFGHJKLMNPQRSTUVWXYZ'[$index], range( 0, $length - 1 ) );

		return array_map(
			fn ( int $index ): Subject => $index === 0
				? $this->subject( $ids[$index], 'Record' )
				: $this->subject( $ids[$index], 'Record', $this->relation( 'Record', $ids[$index - 1] ) ),
			range( 0, $length - 1 )
		);
	}

	private function lengthOf( ?HostSubject $hostSubject ): int {
		return $hostSubject === null ? 0 : 1 + $this->lengthOf( $hostSubject->hostSubject );
	}

	private function picassoWith( Subject ...$otherSubjects ): PageSubjects {
		return new PageSubjects(
			TestSubject::build(
				id: self::PERSON,
				label: 'Pablo Picasso',
				schemaName: TestSchema::reference( 'Person' ),
				statements: new StatementList( [ $this->relation( 'Birth', self::BIRTH ) ] )
			),
			new SubjectMap( ...$otherSubjects )
		);
	}

	private function subject( string $id, string $schema, Statement ...$statements ): Subject {
		return TestSubject::build(
			id: $id,
			label: null,
			schemaName: TestSchema::reference( $schema ),
			statements: new StatementList( $statements )
		);
	}

	private function relation( string $property, string $targetId ): Statement {
		return TestStatement::buildRelation( $property, [ TestRelation::build( targetId: $targetId ) ] );
	}

}
