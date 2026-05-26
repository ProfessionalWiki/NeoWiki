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

	public function testStatementWithUnknownPropertyTypeIsSkipped(): void {
		$schema = $this->newSchema( [ 'Foo' => $this->newNumberProperty() ] );

		$this->assertSame( [], $this->validator->validate(
			new SubjectLabel( 'X' ),
			new StatementList( [
				new Statement( new PropertyName( 'Foo' ), 'unknown-type', new NumberValue( 42 ) ),
			] ),
			$schema,
		) );
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
