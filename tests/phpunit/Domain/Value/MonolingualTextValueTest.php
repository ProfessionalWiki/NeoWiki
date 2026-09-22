<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Value;

use PHPUnit\Framework\TestCase;
use TypeError;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualText;
use ProfessionalWiki\NeoWiki\Domain\Value\MonolingualTextValue;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Value\MonolingualTextValue
 */
class MonolingualTextValueTest extends TestCase {

	public function testTypeIsMonolingualText(): void {
		$this->assertSame( ValueType::MonolingualText, ( new MonolingualTextValue() )->getType() );
	}

	public function testToScalarsGivesOneObjectPerPart(): void {
		$value = new MonolingualTextValue(
			new MonolingualText( 'Zinema', 'eu' ),
			new MonolingualText( 'Cine', 'es' ),
		);

		$this->assertSame(
			[
				[ 'text' => 'Zinema', 'language' => 'eu' ],
				[ 'text' => 'Cine', 'language' => 'es' ],
			],
			$value->toScalars()
		);
	}

	/**
	 * @dataProvider contentlessPartsProvider
	 */
	public function testPartsWithoutTextAreNotStored( MonolingualTextValue $value ): void {
		$this->assertSame( [], $value->toScalars() );
	}

	/**
	 * @dataProvider contentlessPartsProvider
	 */
	public function testValueOfPartsWithoutTextIsEmpty( MonolingualTextValue $value ): void {
		$this->assertTrue( $value->isEmpty() );
	}

	public static function contentlessPartsProvider(): iterable {
		yield 'no parts' => [ new MonolingualTextValue() ];
		yield 'empty text' => [ new MonolingualTextValue( new MonolingualText( '', 'eu' ) ) ];
		yield 'whitespace text' => [ new MonolingualTextValue( new MonolingualText( " \t\n", 'eu' ) ) ];
	}

	public function testPartWithoutTextIsDroppedFromBetweenParts(): void {
		$value = new MonolingualTextValue(
			new MonolingualText( 'Zinema', 'eu' ),
			new MonolingualText( ' ', 'fr' ),
			new MonolingualText( 'Cine', 'es' ),
		);

		$this->assertSame( [ 'eu', 'es' ], array_column( $value->toScalars(), 'language' ) );
	}

	public function testValueWithTextIsNotEmpty(): void {
		$value = new MonolingualTextValue(
			new MonolingualText( '', 'eu' ),
			new MonolingualText( 'Cine', 'es' ),
		);

		$this->assertFalse( $value->isEmpty() );
	}

	public function testGetTextsGivesTheTextOfEachPart(): void {
		$value = new MonolingualTextValue(
			new MonolingualText( 'Zinema', 'eu' ),
			new MonolingualText( 'Cine', 'es' ),
		);

		$this->assertSame( [ 'Zinema', 'Cine' ], $value->getTexts() );
	}

	public function testFromScalarsReadsBackWhatToScalarsWrote(): void {
		$scalars = [
			[ 'text' => 'Zinema', 'language' => 'eu' ],
			[ 'text' => 'Cine', 'language' => 'es' ],
		];

		$this->assertSame( $scalars, MonolingualTextValue::fromScalars( $scalars )->toScalars() );
	}

	/**
	 * @dataProvider malformedScalarsProvider
	 */
	public function testFromScalarsRejectsAPartThatIsNotATextWithALanguage( mixed $json ): void {
		$this->expectException( TypeError::class );
		MonolingualTextValue::fromScalars( $json );
	}

	public static function malformedScalarsProvider(): iterable {
		yield 'bare string' => [ [ 'Zinema' ] ];
		yield 'no language' => [ [ [ 'text' => 'Zinema' ] ] ];
		yield 'no text' => [ [ [ 'language' => 'eu' ] ] ];
		yield 'non-string text' => [ [ [ 'text' => 2019, 'language' => 'eu' ] ] ];
		yield 'non-string language' => [ [ [ 'text' => 'Zinema', 'language' => 42 ] ] ];
	}

	public function testSameLanguageMayRepeat(): void {
		$value = new MonolingualTextValue(
			new MonolingualText( 'Cine', 'es' ),
			new MonolingualText( 'Película', 'es' ),
		);

		$this->assertSame( [ 'Cine', 'Película' ], array_column( $value->toScalars(), 'text' ) );
	}

}
