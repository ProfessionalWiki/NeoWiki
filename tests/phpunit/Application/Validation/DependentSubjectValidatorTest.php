<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Validation\DependentSubjectValidator;
use ProfessionalWiki\NeoWiki\Application\Validation\ProposedSubjectValidator;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\Subject;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectMap;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Validation\Violation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestStatement;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySchemaLookup;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Validation\DependentSubjectValidator
 */
class DependentSubjectValidatorTest extends TestCase {

	private const string HOST = 'sPerson11111111';
	private const string BIRTH = 'sBirth111111111';
	private const string RECORD = 'sRecord11111111';

	private function newValidator(): DependentSubjectValidator {
		return new DependentSubjectValidator( new ProposedSubjectValidator(
			schemaResolver: TestSources::newSchemaResolver( new InMemorySchemaLookup(
				TestSchema::build( name: 'Person' ),
				TestSchema::build(
					name: 'Birth',
					properties: new PropertyDefinitions( [
						'Date' => TestProperty::buildText( required: true, constraintSeverities: [ 'required' => Severity::Error ] ),
						'Record' => TestProperty::buildRelation( targetSchemas: [ 'Record' ] ),
						'Child' => TestProperty::buildRelation( targetSchemas: [ 'Person' ] ),
					] ),
					subjectsAreDependent: true
				),
				TestSchema::build( name: 'Record', subjectsAreDependent: true )
			) ),
			subjectValidator: new SubjectValidator(
				propertyTypeLookup: TestSources::newPropertyTypeRegistry(),
				subjectLookup: new InMemorySubjectLookup(),
				sourceRegistry: TestSources::newRegistry(),
			),
		) );
	}

	private function host(): Subject {
		return TestSubject::build( id: self::HOST, schemaName: TestSchema::reference( 'Person' ) );
	}

	private function birth( Statement ...$statements ): Subject {
		return TestSubject::build(
			id: self::BIRTH,
			label: null,
			schemaName: TestSchema::reference( 'Birth' ),
			statements: new StatementList( $statements )
		);
	}

	private function dated(): Statement {
		return TestStatement::build( property: 'Date', value: '1881-10-25' );
	}

	private function relation( string $property, string $targetId ): Statement {
		return TestStatement::buildRelation( $property, [ TestRelation::build( targetId: $targetId ) ] );
	}

	/**
	 * @return string[]
	 */
	private function codesOf( array $violations ): array {
		return array_map( static fn ( Violation $violation ): string => $violation->code, $violations );
	}

	public function testEachViolationNamesTheDependentSubjectItIsOn(): void {
		$violations = $this->newValidator()->validate( new SubjectMap( $this->birth() ), $this->host(), new SubjectMap() );

		$this->assertEquals( [ new SubjectId( self::BIRTH ) ], array_map( static fn ( Violation $v ) => $v->subjectId, $violations->all ) );
	}

	public function testAnotherDependentSubjectOfTheWriteCountsAsAnExistingRelationTarget(): void {
		$record = TestSubject::build( id: self::RECORD, label: null, schemaName: TestSchema::reference( 'Record' ) );

		$violations = $this->newValidator()->validate(
			new SubjectMap( $this->birth( $this->dated(), $this->relation( 'Record', self::RECORD ) ), $record ),
			$this->host(),
			new SubjectMap()
		);

		$this->assertSame( [], $violations->all );
	}

	public function testTheHostSubjectCountsAsAnExistingRelationTarget(): void {
		$violations = $this->newValidator()->validate(
			new SubjectMap( $this->birth( $this->dated(), $this->relation( 'Child', self::HOST ) ) ),
			$this->host(),
			new SubjectMap()
		);

		$this->assertSame( [], $violations->all );
	}

	public function testABlockingViolationOfANewDependentSubjectIsNew(): void {
		$violations = $this->newValidator()->validate( new SubjectMap( $this->birth() ), $this->host(), new SubjectMap() );

		$this->assertSame( [ 'required' ], $this->codesOf( $violations->newBlocking ) );
	}

	public function testAWarningOfADependentSubjectDoesNotBlock(): void {
		$violations = $this->newValidator()->validate(
			new SubjectMap( $this->birth( $this->dated(), $this->relation( 'Record', 'sMissing1111111' ) ) ),
			$this->host(),
			new SubjectMap()
		);

		$this->assertSame( [], $violations->newBlocking );
	}

	public function testAViolationTheStoredDependentSubjectAlreadyHasIsNotNew(): void {
		$violations = $this->newValidator()->validate(
			new SubjectMap( $this->birth() ),
			$this->host(),
			new SubjectMap( $this->birth() )
		);

		$this->assertSame( [], $violations->newBlocking );
	}

}
