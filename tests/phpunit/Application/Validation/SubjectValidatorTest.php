<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
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
use ProfessionalWiki\NeoWiki\Domain\Value\UnregisteredTypeValue;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Validation\SubjectValidator
 */
class SubjectValidatorTest extends TestCase {

	private SubjectValidator $validator;

	protected function setUp(): void {
		$this->validator = new SubjectValidator(
			propertyTypeLookup: PropertyTypeRegistry::withCoreTypes(),
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

	// --- Helpers ---

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
