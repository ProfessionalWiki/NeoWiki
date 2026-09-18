<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\NormalizationResult;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Validation\Severity;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType
 */
class SelectTypeNormalizeTest extends TestCase {

	private function newProperty(): SelectProperty {
		return new SelectProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			options: [
				new SelectOption( id: 'opt1', label: 'Draft' ),
				new SelectOption( id: 'opt2', label: 'In Review' ),
				new SelectOption( id: 'opt3', label: 'Approved' ),
				new SelectOption( id: 'opt9', label: 'opt1' ),
			],
			multiple: false,
		);
	}

	private function normalize( mixed $raw ): NormalizationResult {
		return ( new SelectType() )->normalizeRawValue( $raw, $this->newProperty() );
	}

	public function testAcceptsOptionId(): void {
		$this->assertSame( 'opt2', $this->normalize( 'opt2' )->value );
	}

	public function testMatchesABareStringAsAnIdBeforeALabel(): void {
		$this->assertSame( 'opt1', $this->normalize( 'opt1' )->value );
	}

	public function testResolvesLabelToId(): void {
		$this->assertSame( 'opt2', $this->normalize( 'In Review' )->value );
	}

	public function testResolvesLabelCaseInsensitively(): void {
		$this->assertSame( 'opt3', $this->normalize( 'aPPRoVed' )->value );
	}

	public function testResolvesLabelWithSurroundingWhitespace(): void {
		$this->assertSame( 'opt3', $this->normalize( '  Approved  ' )->value );
	}

	public function testReportsNoErrorForAResolvableValue(): void {
		$this->assertNull( $this->normalize( 'Approved' )->violation );
	}

	public function testResolvesConsistentIdLabelObject(): void {
		$this->assertSame( 'opt2', $this->normalize( [ 'id' => 'opt2', 'label' => 'In Review' ] )->value );
	}

	public function testResolvesIdLabelObjectWithCaseInsensitiveLabel(): void {
		$this->assertSame( 'opt2', $this->normalize( [ 'id' => 'opt2', 'label' => 'in review' ] )->value );
	}

	public function testResolvesObjectWithOnlyId(): void {
		$this->assertSame( 'opt3', $this->normalize( [ 'id' => 'opt3' ] )->value );
	}

	public function testResolvesObjectWithOnlyLabel(): void {
		$this->assertSame( 'opt3', $this->normalize( [ 'label' => 'Approved' ] )->value );
	}

	public function testObjectWithOnlyIdDoesNotFallBackToLabelMatch(): void {
		$this->assertSame( 'invalid-option', $this->normalize( [ 'id' => 'Approved' ] )->violation->code );
	}

	public function testReportsObjectWithoutIdOrLabel(): void {
		$this->assertSame( 'select-object-without-id-or-label', $this->normalize( [ 'colour' => 'red' ] )->violation->code );
	}

	public function testLeavesUnknownValueInPlace(): void {
		$this->assertSame( 'Nonexistent', $this->normalize( 'Nonexistent' )->value );
	}

	public function testLeavesEmptyValueInPlaceWithoutError(): void {
		$result = $this->normalize( '' );

		$this->assertSame( '', $result->value );
		$this->assertNull( $result->violation );
	}

	public function testLeavesWhitespaceOnlyValueInPlaceWithoutError(): void {
		$result = $this->normalize( '   ' );

		$this->assertSame( '   ', $result->value );
		$this->assertNull( $result->violation );
	}

	public function testReportsValueThatIsNeitherStringNorObject(): void {
		$this->assertSame( 'select-value-not-string-or-object', $this->normalize( 42 )->violation->code );
	}

	public function testResolvesListOfIds(): void {
		$this->assertSame( [ 'opt1', 'opt3' ], $this->normalize( [ 'opt1', 'opt3' ] )->value );
	}

	/**
	 * Normalization canonicalizes every part it is given; reporting that one value was expected is
	 * the validator's job, under `single-value-only`.
	 */
	public function testNormalizesAListForASingleValueProperty(): void {
		$this->assertSame( [ 'opt1', 'opt3' ], $this->normalize( [ 'Draft', 'Approved' ] )->value );
	}

	public function testResolvesListOfMixedForms(): void {
		$raw = [ 'opt1', 'Approved', [ 'id' => 'opt1', 'label' => 'Draft' ] ];

		$this->assertSame( [ 'opt1', 'opt3', 'opt1' ], $this->normalize( $raw )->value );
	}

	public function testAnEmptyListNormalizesWithoutAViolation(): void {
		$result = $this->normalize( [] );

		$this->assertSame( [], $result->value );
		$this->assertNull( $result->violation );
	}

	public function testResolvesListContainingAnEmptyValue(): void {
		$result = $this->normalize( [ '', 'Approved' ] );

		$this->assertSame( [ '', 'opt3' ], $result->value );
		$this->assertNull( $result->violation );
	}

	public function testLeavesTheValueAloneForAPropertyOfAnotherType(): void {
		$definition = ( new TextType() )->buildPropertyDefinitionFromJson(
			new PropertyCore( description: '', required: false, default: null ),
			[]
		);

		$result = ( new SelectType() )->normalizeRawValue( 'Draft', $definition );

		$this->assertSame( 'Draft', $result->value );
		$this->assertNull( $result->violation );
	}

	public function testNamesAnUnknownValueByCodeAndArg(): void {
		$violation = $this->normalize( 'Nonexistent' )->violation;

		$this->assertSame( 'invalid-option', $violation->code );
		$this->assertSame( [ 'Nonexistent' ], $violation->args );
	}

	public function testAScalarViolationIsAtPartZero(): void {
		$this->assertSame( 0, $this->normalize( 'Nonexistent' )->violation->valuePartIndex );
	}

	/**
	 * The dry-run validate endpoints report per value part, so a part that cannot be canonicalized
	 * must not cost the parts that can: leaving the whole list raw would make every good part look
	 * like an invalid option too.
	 */
	public function testLocatesTheFirstUnresolvablePartOfAList(): void {
		$result = $this->normalize( [ 'Draft', 'Bogus1', 'Approved', 'Bogus2' ] );

		$this->assertSame( [ 'opt1', 'Bogus1', 'opt3', 'Bogus2' ], $result->value );
		$this->assertSame( 1, $result->violation->valuePartIndex );
		$this->assertSame( [ 'Bogus1' ], $result->violation->args );
	}

	public function testNamesAnIdLabelMismatchByBothArgs(): void {
		$violation = $this->normalize( [ 'id' => 'opt1', 'label' => 'Approved' ] )->violation;

		$this->assertSame( 'select-id-label-mismatch', $violation->code );
		$this->assertSame( [ 'opt1', 'Approved' ], $violation->args );
	}

	public function testObjectWithOnlyLabelDoesNotFallBackToIdMatch(): void {
		$result = $this->normalize( [ 'label' => 'opt2' ] );

		$this->assertSame( 'invalid-option', $result->violation->code );
		$this->assertSame( [ 'label' => 'opt2' ], $result->value );
	}

	public function testReportsObjectWhoseIdIsNotAString(): void {
		$this->assertSame(
			'select-id-label-not-strings',
			$this->normalize( [ 'id' => 42 ] )->violation->code
		);
	}

	public function testTreatsANullObjectMemberAsAbsent(): void {
		$this->assertSame( 'opt1', $this->normalize( [ 'id' => null, 'label' => 'Draft' ] )->value );
	}

	public function testReportsObjectWhoseLabelIsNotAString(): void {
		$this->assertSame(
			'select-id-label-not-strings',
			$this->normalize( [ 'id' => 'opt1', 'label' => 42 ] )->violation->code
		);
	}

	public function testAViolationCarriesNoPropertyNameAndErrorSeverity(): void {
		$violation = $this->normalize( 'Nonexistent' )->violation;

		$this->assertNull( $violation->propertyName );
		$this->assertSame( Severity::Error, $violation->severity );
	}

}
