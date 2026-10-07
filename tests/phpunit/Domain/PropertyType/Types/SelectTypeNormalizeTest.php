<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\TextType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;

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
				new SelectOption( id: 'opt4', label: 'Öl auf Leinwand' ),
				new SelectOption( id: 'opt9', label: 'opt1' ),
			],
			multiple: false,
		);
	}

	private function normalize( mixed $raw ): mixed {
		return ( new SelectType() )->normalizeRawValue( $raw, $this->newProperty() );
	}

	public function testAcceptsOptionId(): void {
		$this->assertSame( 'opt2', $this->normalize( 'opt2' ) );
	}

	public function testMatchesABareStringAsAnIdBeforeALabel(): void {
		$this->assertSame( 'opt1', $this->normalize( 'opt1' ) );
	}

	public function testResolvesLabelToId(): void {
		$this->assertSame( 'opt2', $this->normalize( 'In Review' ) );
	}

	public function testResolvesLabelCaseInsensitively(): void {
		$this->assertSame( 'opt3', $this->normalize( 'aPPRoVed' ) );
	}

	public function testResolvesNonAsciiLabelCaseInsensitively(): void {
		$this->assertSame( 'opt4', $this->normalize( 'öl auf leinwand' ) );
	}

	public function testResolvesLabelWithSurroundingWhitespace(): void {
		$this->assertSame( 'opt3', $this->normalize( '  Approved  ' ) );
	}

	public function testResolvesConsistentIdLabelObject(): void {
		$this->assertSame( 'opt2', $this->normalize( [ 'id' => 'opt2', 'label' => 'In Review' ] ) );
	}

	public function testResolvesIdLabelObjectWithCaseInsensitiveLabel(): void {
		$this->assertSame( 'opt2', $this->normalize( [ 'id' => 'opt2', 'label' => 'in review' ] ) );
	}

	public function testResolvesObjectWithOnlyId(): void {
		$this->assertSame( 'opt3', $this->normalize( [ 'id' => 'opt3' ] ) );
	}

	public function testResolvesObjectWithOnlyLabel(): void {
		$this->assertSame( 'opt3', $this->normalize( [ 'label' => 'Approved' ] ) );
	}

	public function testTreatsANullObjectMemberAsAbsent(): void {
		$this->assertSame( 'opt1', $this->normalize( [ 'id' => null, 'label' => 'Draft' ] ) );
	}

	public function testLeavesAStringNamingNoOptionAsSent(): void {
		$this->assertSame( 'Nonexistent', $this->normalize( 'Nonexistent' ) );
	}

	public function testLeavesEmptyValueInPlace(): void {
		$this->assertSame( '', $this->normalize( '' ) );
	}

	public function testLeavesWhitespaceOnlyValueInPlace(): void {
		$this->assertSame( '   ', $this->normalize( '   ' ) );
	}

	public function testResolvesListOfIds(): void {
		$this->assertSame( [ 'opt1', 'opt3' ], $this->normalize( [ 'opt1', 'opt3' ] ) );
	}

	/**
	 * Normalization canonicalizes every part it is given; reporting that one value was expected is
	 * the validator's job, under `single-value-only`.
	 */
	public function testNormalizesAListForASingleValueProperty(): void {
		$this->assertSame( [ 'opt1', 'opt3' ], $this->normalize( [ 'Draft', 'Approved' ] ) );
	}

	public function testResolvesListOfMixedForms(): void {
		$raw = [ 'opt1', 'Approved', [ 'id' => 'opt2', 'label' => 'In Review' ] ];

		$this->assertSame( [ 'opt1', 'opt3', 'opt2' ], $this->normalize( $raw ) );
	}

	public function testResolvesTheOtherPartsOfAListThatHasPartsNamingNoOption(): void {
		$this->assertSame(
			[ 'opt1', 'Bogus1', 'opt3', 'Bogus2' ],
			$this->normalize( [ 'Draft', 'Bogus1', 'Approved', 'Bogus2' ] )
		);
	}

	public function testDropsAPartNamingTheSameOptionAsAnEarlierPart(): void {
		$this->assertSame( [ 'opt3', 'opt1', 'opt2' ], $this->normalize( [ 'opt3', 'opt1', 'Draft', 'opt2' ] ) );
	}

	public function testAnEmptyListStaysEmpty(): void {
		$this->assertSame( [], $this->normalize( [] ) );
	}

	public function testResolvesListContainingAnEmptyValue(): void {
		$this->assertSame( [ '', 'opt3' ], $this->normalize( [ '', 'Approved' ] ) );
	}

	public function testLeavesTheValueAloneForAPropertyOfAnotherType(): void {
		$definition = ( new TextType() )->buildPropertyDefinitionFromJson(
			new PropertyCore( description: '', required: false, default: null ),
			[]
		);

		$this->assertSame( 'Draft', ( new SelectType() )->normalizeRawValue( 'Draft', $definition ) );
	}

	public function testRejectsAValueThatIsNeitherStringNorObject(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->normalize( 42 );
	}

	public function testRejectsAListPartThatIsNeitherStringNorObject(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->normalize( [ 'Draft', 42 ] );
	}

	public function testRejectsAnObjectWithoutIdOrLabel(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->normalize( [ 'colour' => 'red' ] );
	}

	public function testRejectsAnObjectWhoseIdIsNotAString(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->normalize( [ 'id' => 42, 'label' => 'Draft' ] );
	}

	public function testRejectsAnObjectWhoseLabelIsNotAString(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->normalize( [ 'id' => 'opt1', 'label' => 42 ] );
	}

	public function testRejectsAnIdLabelObjectNamingTwoOptions(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->expectExceptionMessage( 'option "opt1" is not labelled "Approved"' );
		$this->normalize( [ 'id' => 'opt1', 'label' => 'Approved' ] );
	}

	/**
	 * Unlike a bare string, an object naming an id never falls back to a label match, so a stale
	 * id cannot silently become some other option.
	 */
	public function testRejectsAnObjectWhoseIdNamesNoOption(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->normalize( [ 'id' => 'Approved' ] );
	}

	/**
	 * Storing the label as sent would read as the id of the option that has it as its id.
	 */
	public function testRejectsAnObjectWhoseLabelNamesNoOption(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->normalize( [ 'label' => 'opt2' ] );
	}

}
