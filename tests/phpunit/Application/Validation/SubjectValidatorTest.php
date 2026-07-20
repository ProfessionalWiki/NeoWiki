<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Relation\Relation;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Statement;
use ProfessionalWiki\NeoWiki\Domain\Subject\StatementList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectLabel;
use ProfessionalWiki\NeoWiki\Domain\Value\NumberValue;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubject;
use ProfessionalWiki\NeoWiki\Tests\TestDoubles\InMemorySubjectLookup;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator
 */
class SubjectValidatorTest extends TestCase {

	private const string TARGET_SCHEMA = 'Person';
	private const string MATCHING_TARGET_ID = 'srt111111111aaa';
	private const string MISMATCHING_TARGET_ID = 'srt111111111bbb';
	private const string NONEXISTENT_TARGET_ID = 'srt111111111eee';

	private SubjectValidator $validator;

	protected function setUp(): void {
		$this->validator = new SubjectValidator(
			propertyTypeLookup: PropertyTypeRegistry::withCoreTypes(),
			subjectLookup: $this->newSubjectLookup(),
		);
	}

	private function newSubjectLookup(): InMemorySubjectLookup {
		return new InMemorySubjectLookup(
			TestSubject::build( id: 'srt111111111ccc', schemaName: new SchemaName( 'DecoySchema' ) ),
			TestSubject::build( id: self::MATCHING_TARGET_ID, schemaName: new SchemaName( self::TARGET_SCHEMA ) ),
			TestSubject::build( id: self::MISMATCHING_TARGET_ID, schemaName: new SchemaName( 'Company' ) ),
			TestSubject::build( id: 'srt111111111ddd', schemaName: new SchemaName( 'DecoySchema' ) ),
		);
	}

	public function testValidSubjectReturnsNoViolations(): void {
		$schema = $this->newSchema( [ 'Age' => $this->newNumberProperty() ] );

		$this->assertSame( [], $this->validator->validate(
			new SubjectLabel( 'John Doe' ),
			new StatementList( [
				new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 42 ) ),
			] ),
			$schema,
		) );
	}

	public function testEmptyLabelReturnsLabelRequired(): void {
		$schema = $this->newSchema( [] );

		$violations = $this->validator->validate(
			new SubjectLabel( '' ),
			new StatementList( [] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'label-required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
	}

	public function testWhitespaceOnlyLabelReturnsLabelRequired(): void {
		$schema = $this->newSchema( [] );

		$violations = $this->validator->validate(
			new SubjectLabel( '   ' ),
			new StatementList( [] ),
			$schema,
		);

		$this->assertSame( 'label-required', $violations[0]->code );
	}

	public function testStatementWithUnknownPropertyIsSkipped(): void {
		$schema = $this->newSchema( [ 'Known' => $this->newNumberProperty() ] );

		$this->assertSame( [], $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'Unknown' ), 'number', new NumberValue( 1 ) ),
			] ),
			$schema,
		) );
	}

	public function testStatementViolationHasPropertyNameAttached(): void {
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty( maximum: 100 ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 999 ) ),
			] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'max-value', $violations[0]->code );
		$this->assertEquals( new PropertyName( 'Age' ), $violations[0]->propertyName );
	}

	public function testMultipleStatementViolationsAreAccumulated(): void {
		$schema = $this->newSchema( [
			'A' => $this->newNumberProperty( maximum: 10 ),
			'B' => $this->newNumberProperty( minimum: 100 ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'A' ), 'number', new NumberValue( 999 ) ),
				new Statement( new PropertyName( 'B' ), 'number', new NumberValue( 1 ) ),
			] ),
			$schema,
		);

		$this->assertCount( 2, $violations );
		$codes = array_map( static fn( $v ) => $v->code, $violations );
		$this->assertContains( 'max-value', $codes );
		$this->assertContains( 'min-value', $codes );
	}

	public function testLabelViolationComesBeforeStatementViolations(): void {
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty( maximum: 100 ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( '' ),
			new StatementList( [
				new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 999 ) ),
			] ),
			$schema,
		);

		$this->assertSame( 'label-required', $violations[0]->code );
		$this->assertSame( 'max-value', $violations[1]->code );
	}

	public function testMissingRequiredPropertyReturnsRequired(): void {
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty( required: true ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertEquals( new PropertyName( 'Age' ), $violations[0]->propertyName );
	}

	public function testOptionalMissingPropertyReturnsNoViolation(): void {
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty( required: false ),
		] );

		$this->assertSame( [], $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [] ),
			$schema,
		) );
	}

	public function testRequiredPropertyWithStatementPresentDoesNotEmitFromSchemaIteration(): void {
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty( required: true ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 42 ) ),
			] ),
			$schema,
		);

		// Statement is present and content-valid; required is satisfied.
		$this->assertSame( [], $violations );
	}

	public function testMultipleMissingRequiredPropertiesAllSurface(): void {
		$schema = $this->newSchema( [
			'A' => $this->newNumberProperty( required: true ),
			'B' => $this->newNumberProperty( required: false ),
			'C' => $this->newNumberProperty( required: true ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [] ),
			$schema,
		);

		$this->assertCount( 2, $violations );
		$missing = array_map(
			static fn( $v ) => (string)$v->propertyName,
			$violations
		);
		$this->assertContains( 'A', $missing );
		$this->assertContains( 'C', $missing );
		$this->assertNotContains( 'B', $missing );
	}

	public function testWriterTypeDiffersFromCurrentTypeEmitsTypeMismatch(): void {
		// Schema currently declares Age as a Number property, but the Statement
		// was written when Age was something else (here: a text property).
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty(),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'Age' ), 'text', new NumberValue( 42 ) ),
			] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'type-mismatch', $violations[0]->code );
		$this->assertEquals( new PropertyName( 'Age' ), $violations[0]->propertyName );
		$this->assertSame( [ 'text', 'number' ], $violations[0]->args );
	}

	public function testTypeMismatchSkipsPerTypeValidation(): void {
		// If type-mismatch fires, the per-type validator must not also run —
		// it would either no-op against the wrong-typed PropertyDefinition or
		// emit a redundant violation.
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty( maximum: 10 ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'Age' ), 'text', new NumberValue( 999 ) ),
			] ),
			$schema,
		);

		// Only the type-mismatch fires; no max-value even though 999 > 10.
		$this->assertCount( 1, $violations );
		$this->assertSame( 'type-mismatch', $violations[0]->code );
	}

	public function testTypeMismatchOnRequiredPropertySuppressesMissingRequired(): void {
		// A Statement IS present (just under the wrong type), so the
		// schema-iteration loop should NOT also emit 'required'.
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty( required: true ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'Age' ), 'text', new NumberValue( 42 ) ),
			] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'type-mismatch', $violations[0]->code );
	}

	public function testUnregisteredWriterTypeStillEmitsTypeMismatch(): void {
		// Replaces the pre-refactor testStatementWithUnknownPropertyTypeIsSkipped:
		// when the writer's type is a string not in the PropertyType registry
		// AND differs from the schema's current type, type-mismatch still fires
		// (the comparison is string-vs-string; registry membership is checked
		// only when the types match, for the per-type validate dispatch).
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty(),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'Age' ), 'unknown-type', new NumberValue( 42 ) ),
			] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'type-mismatch', $violations[0]->code );
		$this->assertSame( [ 'unknown-type', 'number' ], $violations[0]->args );
	}

	public function testMatchingTypeRunsPerTypeValidation(): void {
		// Sanity: when writer's type matches current type, per-type validation
		// runs normally.
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty( maximum: 10 ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'Age' ), 'number', new NumberValue( 999 ) ),
			] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'max-value', $violations[0]->code );
	}

	public function testMissingRequiredViolationsAppearAfterStatementViolations(): void {
		$schema = $this->newSchema( [
			'Present' => $this->newNumberProperty( maximum: 10 ),
			'Missing' => $this->newNumberProperty( required: true ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'Present' ), 'number', new NumberValue( 999 ) ),
			] ),
			$schema,
		);

		$this->assertCount( 2, $violations );
		$this->assertSame( 'max-value', $violations[0]->code );
		$this->assertSame( 'required', $violations[1]->code );
		$this->assertEquals( new PropertyName( 'Missing' ), $violations[1]->propertyName );
	}

	public function testUnregisteredPropertyTypeReturnsNonBlockingViolation(): void {
		$schema = $this->newSchema( [ 'Swatch' => $this->newUnregisteredTypeProperty() ] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement(
					new PropertyName( 'Swatch' ),
					'color',
					new UnregisteredTypeValue( 'color', [ '#ff5733' ] )
				),
			] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'unregistered-type', $violations[0]->code );
		$this->assertEquals( new PropertyName( 'Swatch' ), $violations[0]->propertyName );
		$this->assertSame( [ 'color' ], $violations[0]->args );
		$this->assertFalse( $violations[0]->isBlocking() );
	}

	/**
	 * A required property whose type is unregistered cannot be satisfied: there is no
	 * editor to enter a value with. Blocking on it would make the Subject uncreatable.
	 */
	public function testRequiredPropertyOfUnregisteredTypeReportsTheTypeInsteadOfRequired(): void {
		$schema = $this->newSchema( [ 'Swatch' => $this->newUnregisteredTypeProperty( required: true ) ] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'unregistered-type', $violations[0]->code );
		$this->assertEquals( new PropertyName( 'Swatch' ), $violations[0]->propertyName );
		$this->assertFalse( $violations[0]->isBlocking() );
	}

	public function testRequiredPropertyOfRegisteredTypeStillReportsRequired(): void {
		$schema = $this->newSchema( [
			'Age' => $this->newNumberProperty( required: true ),
			'Swatch' => $this->newUnregisteredTypeProperty( required: true ),
		] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [] ),
			$schema,
		);

		$this->assertCount( 2, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertTrue( $violations[0]->isBlocking() );
		$this->assertSame( 'unregistered-type', $violations[1]->code );
	}

	public function testRelationTargetWithMismatchedSchemaReturnsSchemaMismatchError(): void {
		$schema = $this->newSchema( [ 'Links' => $this->newRelationProperty() ] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [ $this->newRelationStatement( self::MISMATCHING_TARGET_ID ) ] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'relation-target-schema-mismatch', $violations[0]->code );
		$this->assertEquals( new PropertyName( 'Links' ), $violations[0]->propertyName );
		$this->assertSame( [ self::TARGET_SCHEMA, 'Company' ], $violations[0]->args );
		$this->assertTrue( $violations[0]->isBlocking() );
	}

	public function testRelationTargetWithMatchingSchemaReturnsNoViolation(): void {
		$schema = $this->newSchema( [ 'Links' => $this->newRelationProperty() ] );

		$this->assertSame( [], $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [ $this->newRelationStatement( self::MATCHING_TARGET_ID ) ] ),
			$schema,
		) );
	}

	public function testNonexistentRelationTargetReturnsNonBlockingNotFoundWarning(): void {
		$schema = $this->newSchema( [ 'Links' => $this->newRelationProperty() ] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [ $this->newRelationStatement( self::NONEXISTENT_TARGET_ID ) ] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'relation-target-not-found', $violations[0]->code );
		$this->assertEquals( new PropertyName( 'Links' ), $violations[0]->propertyName );
		$this->assertSame( [ self::NONEXISTENT_TARGET_ID ], $violations[0]->args );
		$this->assertFalse( $violations[0]->isBlocking() );
	}

	public function testNonexistentRelationTargetDoesNotAlsoEmitSchemaMismatch(): void {
		$schema = $this->newSchema( [ 'Links' => $this->newRelationProperty() ] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [ $this->newRelationStatement( self::NONEXISTENT_TARGET_ID ) ] ),
			$schema,
		);

		$codes = array_map( static fn ( $v ) => $v->code, $violations );
		$this->assertNotContains( 'relation-target-schema-mismatch', $codes );
	}

	public function testSingleValueRelationWithTwoTargetsSurfacesSingleValueOnly(): void {
		$schema = $this->newSchema( [ 'Links' => $this->newRelationProperty( multiple: false ) ] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [ $this->newRelationStatement( self::MATCHING_TARGET_ID, self::MATCHING_TARGET_ID ) ] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'single-value-only', $violations[0]->code );
		$this->assertEquals( new PropertyName( 'Links' ), $violations[0]->propertyName );
	}

	public function testMultiValueRelationReportsEachTargetViolationAtItsOwnPartIndex(): void {
		$schema = $this->newSchema( [ 'Links' => $this->newRelationProperty( multiple: true ) ] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				$this->newRelationStatement( self::MISMATCHING_TARGET_ID, self::MISMATCHING_TARGET_ID ),
			] ),
			$schema,
		);

		// Distinct valuePartIndex per target is what lets ViolationDiff treat a newly-added bad
		// target as new on the Replace enforcement path, rather than collapsing it into a
		// pre-existing same-code violation on the same property.
		$this->assertCount( 2, $violations );
		$this->assertSame( 'relation-target-schema-mismatch', $violations[0]->code );
		$this->assertSame( 0, $violations[0]->valuePartIndex );
		$this->assertSame( 'relation-target-schema-mismatch', $violations[1]->code );
		$this->assertSame( 1, $violations[1]->valuePartIndex );
	}

	public function testRelationTargetViolationCarriesItsArrayPositionNotAViolationCounter(): void {
		$schema = $this->newSchema( [ 'Links' => $this->newRelationProperty( multiple: true ) ] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				$this->newRelationStatement( self::MATCHING_TARGET_ID, self::MISMATCHING_TARGET_ID ),
			] ),
			$schema,
		);

		// The valid target at index 0 emits nothing, so the sole violation belongs to the target at
		// index 1. Pinning the position here is what the all-targets-invalid case above cannot do:
		// there, the array position and the ordinal of the violation itself agree, so a counter-based
		// implementation would pass it. ViolationDiff keys on valuePartIndex, so the difference
		// decides whether an edit's violations line up with the pre-existing ones.
		$this->assertCount( 1, $violations );
		$this->assertSame( 'relation-target-schema-mismatch', $violations[0]->code );
		$this->assertSame( 1, $violations[0]->valuePartIndex );
	}

	public function testNonexistentRelationTargetCarriesItsArrayPosition(): void {
		$schema = $this->newSchema( [ 'Links' => $this->newRelationProperty( multiple: true ) ] );

		$violations = $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				$this->newRelationStatement( self::MATCHING_TARGET_ID, self::NONEXISTENT_TARGET_ID ),
			] ),
			$schema,
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'relation-target-not-found', $violations[0]->code );
		$this->assertSame( 1, $violations[0]->valuePartIndex );
	}

	// --- Helpers ---

	private function newRelationProperty( bool $multiple = true ): RelationProperty {
		return RelationProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'relation' => 'has', 'targetSchema' => self::TARGET_SCHEMA, 'multiple' => $multiple ],
		);
	}

	private function newRelationStatement( string ...$targetIds ): Statement {
		$relations = array_map(
			static fn ( string $targetId ): Relation => TestRelation::build( targetId: $targetId ),
			$targetIds,
		);

		return new Statement( new PropertyName( 'Links' ), 'relation', new RelationValue( ...$relations ) );
	}

	private function newSchema( array $properties ): Schema {
		return new Schema(
			name: new SchemaName( 'TestSchema' ),
			description: '',
			properties: $this->newPropertyDefinitions( $properties ),
		);
	}

	/**
	 * @param array<string, PropertyDefinition> $properties
	 */
	private function newPropertyDefinitions( array $properties ): PropertyDefinitions {
		return new PropertyDefinitions( $properties );
	}

	private function newUnregisteredTypeProperty( bool $required = false ): UnregisteredTypeProperty {
		return UnregisteredTypeProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'type' => 'color', 'allowedColors' => [ '#ff5733' ] ],
		);
	}

	private function newNumberProperty(
		bool $required = false,
		float|int|null $minimum = null,
		float|int|null $maximum = null,
	): NumberProperty {
		return NumberProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[ 'minimum' => $minimum, 'maximum' => $maximum, 'precision' => null ],
		);
	}

}
