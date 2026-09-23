<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\MonolingualTextType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\MonolingualTextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualText;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualTextValue;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\MonolingualTextType
 */
class MonolingualTextTypeValidateTest extends TestCase {

	private MonolingualTextType $type;

	protected function setUp(): void {
		$this->type = new MonolingualTextType();
	}

	public function testRequiredAndEmptyReturnsRequiredViolation(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(),
			$this->newProperty( required: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
		$this->assertNull( $violations[0]->valuePartIndex );
	}

	public function testRequiredAndOneLanguagePresentReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue( new MonolingualText( 'Zinema', 'eu' ) ),
			$this->newProperty( required: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testOptionalAndEmptyReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(),
			$this->newProperty( required: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testValueOfAnotherTypeReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new StringValue( 'Zinema' ),
			$this->newProperty( required: true, uniqueItems: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testUniqueItemsWithSameTextAndLanguageReturnsUniqueViolation(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Zinema', 'eu' ),
				new MonolingualText( 'Cine', 'es' ),
				new MonolingualText( 'Zinema', 'eu' ),
			),
			$this->newProperty( required: false, uniqueItems: true ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'unique', $violations[0]->code );
		$this->assertNull( $violations[0]->valuePartIndex );
	}

	public function testUniqueItemsWithSameTextInDifferentLanguagesReturnsNoViolation(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Cine', 'es' ),
				new MonolingualText( 'Cine', 'gl' ),
			),
			$this->newProperty( required: false, uniqueItems: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testUniqueItemsWithDifferentTextsInOneLanguageReturnsNoViolation(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Cine', 'es' ),
				new MonolingualText( 'Película', 'es' ),
			),
			$this->newProperty( required: false, uniqueItems: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testUniqueItemsFalseWithDuplicatesReturnsNoViolation(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Zinema', 'eu' ),
				new MonolingualText( 'Zinema', 'eu' ),
			),
			$this->newProperty( required: false, uniqueItems: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testUniqueViolationUsesErrorWhenUniqueItemsAnnotated(): void {
		$definition = $this->newAnnotatedProperty( [ 'uniqueItems' => [ 'severity' => 'error' ] ] );

		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Zinema', 'eu' ),
				new MonolingualText( 'Zinema', 'eu' ),
			),
			$definition
		);

		$this->assertSame( 'unique', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
	}

	public function testPartShorterThanMinLengthProducesMinLengthViolation(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Zinema', 'eu' ),
				new MonolingualText( 'Ci', 'es' ),
				new MonolingualText( 'Cinéma', 'fr' ),
			),
			$this->newProperty( required: false, minLength: 3 )
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'min-length', $violations[0]->code );
		$this->assertSame( [ 3 ], $violations[0]->args );
		$this->assertSame( 1, $violations[0]->valuePartIndex );
	}

	public function testMinLengthViolationUsesErrorWhenMinLengthAnnotated(): void {
		$definition = $this->newAnnotatedProperty( [ 'minLength' => [ 'value' => 3, 'severity' => 'error' ] ] );

		$violations = $this->type->validate(
			new MonolingualTextValue( new MonolingualText( 'Ci', 'es' ) ),
			$definition
		);

		$this->assertSame( 'min-length', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
	}

	public function testPartLongerThanMaxLengthProducesMaxLengthViolation(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Cine', 'es' ),
				new MonolingualText( 'Zinemaldia', 'eu' ),
				new MonolingualText( 'Kino', 'de' ),
			),
			$this->newProperty( required: false, maxLength: 4 )
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'max-length', $violations[0]->code );
		$this->assertSame( [ 4 ], $violations[0]->args );
		$this->assertSame( 1, $violations[0]->valuePartIndex );
	}

	public function testMaxLengthViolationUsesErrorWhenMaxLengthAnnotated(): void {
		$definition = $this->newAnnotatedProperty( [ 'maxLength' => [ 'value' => 4, 'severity' => 'error' ] ] );

		$violations = $this->type->validate(
			new MonolingualTextValue( new MonolingualText( 'Zinemaldia', 'eu' ) ),
			$definition
		);

		$this->assertSame( 'max-length', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
	}

	public function testTrimmedLengthBelowMinimumProducesViolation(): void {
		// Trimmed 'Ci' has length 2 (< 3); the raw 6-character value would not.
		$violations = $this->type->validate(
			new MonolingualTextValue( new MonolingualText( '  Ci  ', 'es' ) ),
			$this->newProperty( required: false, minLength: 3 )
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'min-length', $violations[0]->code );
	}

	public function testValueWithinLengthBoundsProducesNoViolation(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue( new MonolingualText( 'Cine', 'es' ) ),
			$this->newProperty( required: false, minLength: 2, maxLength: 5 )
		);

		$this->assertSame( [], $violations );
	}

	public function testRequiredEmptyValueDoesNotAlsoReportLengthViolation(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue( new MonolingualText( '', 'es' ) ),
			$this->newProperty( required: true, minLength: 3 )
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'required', $violations[0]->code );
	}

	public function testLengthAndUniqueViolationsBothReturnedWhenBothConditionsTrigger(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Ci', 'es' ),
				new MonolingualText( 'Ci', 'es' ),
			),
			$this->newProperty( required: false, uniqueItems: true, minLength: 3 ),
		);

		$codes = array_map( fn( $v ) => $v->code, $violations );
		$this->assertContains( 'min-length', $codes );
		$this->assertContains( 'unique', $codes );
	}

	public function testSeveralPartsWithoutMultipleReturnSingleValueOnlyViolation(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Zinema', 'eu' ),
				new MonolingualText( 'Cine', 'es' ),
			),
			$this->newProperty( required: false, multiple: false ),
		);

		$this->assertCount( 1, $violations );
		$this->assertSame( 'single-value-only', $violations[0]->code );
		$this->assertNull( $violations[0]->propertyName );
		$this->assertNull( $violations[0]->valuePartIndex );
	}

	public function testOnePartWithoutMultipleReturnsNoViolations(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue( new MonolingualText( 'Zinema', 'eu' ) ),
			$this->newProperty( required: false, multiple: false ),
		);

		$this->assertSame( [], $violations );
	}

	public function testSeveralPartsWithMultipleReturnNoViolations(): void {
		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Zinema', 'eu' ),
				new MonolingualText( 'Cine', 'es' ),
			),
			$this->newProperty( required: false, multiple: true ),
		);

		$this->assertSame( [], $violations );
	}

	public function testSingleValueOnlyUsesErrorWhenMultipleAnnotated(): void {
		$definition = PropertyDefinition::fromJson(
			[ 'type' => 'monolingualText', 'multiple' => [ 'value' => false, 'severity' => 'error' ] ],
			PropertyTypeRegistry::withCoreTypes( TestSources::newSchemaReferenceParser() ),
		);

		$violations = $this->type->validate(
			new MonolingualTextValue(
				new MonolingualText( 'Zinema', 'eu' ),
				new MonolingualText( 'Cine', 'es' ),
			),
			$definition
		);

		$this->assertSame( 'single-value-only', $violations[0]->code );
		$this->assertSame( Severity::Error, $violations[0]->severity );
	}

	public function testSearchTextIsTheTexts(): void {
		$value = new MonolingualTextValue(
			new MonolingualText( 'Zinema', 'eu' ),
			new MonolingualText( 'Cine', 'es' ),
		);

		$this->assertSame( [ 'Zinema', 'Cine' ], $this->type->searchText( $value, null ) );
	}

	public function testSearchTextOfAnotherValueTypeIsEmpty(): void {
		$this->assertSame( [], $this->type->searchText( new StringValue( 'Zinema' ), null ) );
	}

	private function newProperty(
		bool $required,
		bool $uniqueItems = false,
		?int $minLength = null,
		?int $maxLength = null,
		bool $multiple = true,
	): MonolingualTextProperty {
		return MonolingualTextProperty::fromPartialJson(
			new PropertyCore( description: '', required: $required, default: null ),
			[
				'multiple' => $multiple,
				'uniqueItems' => $uniqueItems,
				'minLength' => $minLength,
				'maxLength' => $maxLength,
			],
		);
	}

	/**
	 * @param array<string, mixed> $constraints
	 */
	private function newAnnotatedProperty( array $constraints ): PropertyDefinition {
		return PropertyDefinition::fromJson(
			[ 'type' => 'monolingualText', 'multiple' => true ] + $constraints,
			PropertyTypeRegistry::withCoreTypes( TestSources::newSchemaReferenceParser() ),
		);
	}

}
