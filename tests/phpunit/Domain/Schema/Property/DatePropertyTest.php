<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DatePrecision;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\PartialDate;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\JsonSchemaAssertions;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty
 */
class DatePropertyTest extends TestCase {

	use JsonSchemaAssertions;

	public function testPropertyTypeIsDate(): void {
		$property = TestProperty::buildDate();

		$this->assertSame( 'date', $property->getPropertyType() );
	}

	public function testMinimumAndMaximumAreNullByDefault(): void {
		$property = TestProperty::buildDate();

		$this->assertNull( $property->getMinimum() );
		$this->assertFalse( $property->hasMinimum() );
		$this->assertNull( $property->getMaximum() );
		$this->assertFalse( $property->hasMaximum() );
	}

	public function testMinimumAndMaximumFromJson(): void {
		$property = DateProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'minimum' => '2020-01-01', 'maximum' => '2030-12-31' ]
		);

		$this->assertSame( '2020-01-01', $property->getMinimum() );
		$this->assertTrue( $property->hasMinimum() );
		$this->assertSame( '2030-12-31', $property->getMaximum() );
		$this->assertTrue( $property->hasMaximum() );
	}

	public function testSerializationRoundTrip(): void {
		$property = DateProperty::fromPartialJson(
			new PropertyCore( description: 'A date', required: true, default: '2025-06-15' ),
			[ 'minimum' => '2020-01-01', 'maximum' => '2030-12-31' ]
		);

		$json = $property->toJson();

		$this->assertSame( 'date', $json['type'] );
		$this->assertSame( 'A date', $json['description'] );
		$this->assertTrue( $json['required'] );
		$this->assertSame( '2025-06-15', $json['default'] );
		$this->assertSame( '2020-01-01', $json['minimum'] );
		$this->assertSame( '2030-12-31', $json['maximum'] );
	}

	public function testBuildPropertyDefinitionFromJsonViaType(): void {
		$type = new DateType();
		$core = new PropertyCore( description: '', required: false, default: null );

		$property = $type->buildPropertyDefinitionFromJson( $core, [
			'minimum' => '2020-01-01',
		] );

		$this->assertInstanceOf( DateProperty::class, $property );
		$this->assertSame( '2020-01-01', $property->getMinimum() );
		$this->assertNull( $property->getMaximum() );
	}

	public function testConstructorAcceptsLeapDay(): void {
		$property = new DateProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: '2024-02-29',
			maximum: null,
			minPrecision: null,
		);

		$this->assertSame( '2024-02-29', $property->getMinimum() );
	}

	public function testBoundsAndDefaultAcceptYearAndMonthPrecision(): void {
		$property = DateProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: '1984' ),
			[ 'minimum' => '1900', 'maximum' => '1999-12' ]
		);

		$this->assertSame( '1900', $property->getMinimum() );
		$this->assertSame( '1999-12', $property->getMaximum() );
		$this->assertSame( '1984', $property->getDefault() );
	}

	public function testMinPrecisionIsNullByDefault(): void {
		$this->assertNull( TestProperty::buildDate()->getMinPrecision() );
	}

	public function testMinPrecisionFromJson(): void {
		$property = DateProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'minPrecision' => 'month' ]
		);

		$this->assertSame( DatePrecision::Month, $property->getMinPrecision() );
	}

	public function testMinPrecisionSerializes(): void {
		$property = DateProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'minPrecision' => 'day' ]
		);

		$this->assertSame( 'day', $property->toJson()['minPrecision'] );
	}

	public function testUnsetMinPrecisionSerializesAsNull(): void {
		$this->assertNull( TestProperty::buildDate()->toJson()['minPrecision'] );
	}

	/**
	 * @dataProvider invalidMinPrecisionProvider
	 */
	public function testFromPartialJsonRejectsInvalidMinPrecision( mixed $invalid ): void {
		$this->expectException( InvalidArgumentException::class );

		DateProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'minPrecision' => $invalid ]
		);
	}

	public static function invalidMinPrecisionProvider(): iterable {
		yield 'year constrains nothing' => [ 'year' ];
		yield 'unknown precision' => [ 'week' ];
		yield 'wrong case' => [ 'Day' ];
		yield 'not a string' => [ 2 ];
	}

	/**
	 * @dataProvider malformedDateProvider
	 */
	public function testConstructorRejectsMalformedMinimum( string $malformed ): void {
		$this->expectException( InvalidArgumentException::class );

		new DateProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: $malformed,
			maximum: null,
			minPrecision: null,
		);
	}

	/**
	 * @dataProvider malformedDateProvider
	 */
	public function testConstructorRejectsMalformedMaximum( string $malformed ): void {
		$this->expectException( InvalidArgumentException::class );

		new DateProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: null,
			maximum: $malformed,
			minPrecision: null,
		);
	}

	/**
	 * @dataProvider malformedDateProvider
	 */
	public function testConstructorRejectsMalformedDefault( string $malformed ): void {
		$this->expectException( InvalidArgumentException::class );

		new DateProperty(
			core: new PropertyCore( description: '', required: false, default: $malformed ),
			minimum: null,
			maximum: null,
			minPrecision: null,
		);
	}

	/**
	 * @dataProvider malformedDateProvider
	 */
	public function testFromPartialJsonRejectsMalformedBounds( string $malformed ): void {
		$this->expectException( InvalidArgumentException::class );

		DateProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'minimum' => $malformed ]
		);
	}

	public static function malformedDateProvider(): iterable {
		yield 'has time component' => [ '2025-06-15T12:00:00Z' ];
		yield 'has midnight time component' => [ '2025-06-15T00:00:00' ];
		yield 'invalid month' => [ '2025-13-01' ];
		yield 'invalid day' => [ '2025-02-30' ];
		yield 'non leap year Feb 29' => [ '2025-02-29' ];
		yield 'garbage' => [ 'not-a-date' ];
		yield 'empty string' => [ '' ];
	}

	public function testValueIsOneFormattedString(): void {
		$this->assertSame(
			[
				'type' => 'array',
				'items' => [
					'type' => 'string',
					'pattern' => PartialDate::PATTERN,
					'anyOf' => [ [ 'maxLength' => 7 ], [ 'format' => 'date' ] ],
				],
				'maxItems' => 1,
			],
			TestProperty::buildDate()->toJsonSchema()
		);
	}

	public function testBoundsAreNotExpressed(): void {
		$value = TestProperty::buildDate( minimum: '2020-01-01', maximum: '2030-12-31' )->toJsonSchema();

		$this->assertArrayNotHasKey( 'minimum', $value, 'Standard JSON Schema cannot order dates.' );
		$this->assertArrayNotHasKey( 'maximum', $value, 'Standard JSON Schema cannot order dates.' );
		$this->assertArrayNotHasKey( 'minimum', $value['items'] );
		$this->assertArrayNotHasKey( 'maximum', $value['items'] );
	}

	/**
	 * @dataProvider datePatternProvider
	 */
	public function testPatternAloneJudgesADate( string $value, bool $valid ): void {
		$this->assertSame( $valid, $this->jsonSchemaAccepts( TestProperty::buildDate()->toJsonSchema()['items'], $value ) );
	}

	public static function datePatternProvider(): iterable {
		yield 'a full date' => [ '2025-06-15', true ];
		yield 'a year and month' => [ '2025-06', true ];
		yield 'a year' => [ '2025', true ];
		yield 'a year before year one' => [ '-0500-06-15', false ];
		yield 'a year of fewer than four digits' => [ '984', false ];
		yield 'an impossible month' => [ '2025-13-01', false ];
		yield 'month zero' => [ '2025-00-15', false ];
		yield 'an impossible day' => [ '2025-06-32', false ];
		yield 'day zero' => [ '2025-06-00', false ];
		yield 'unpadded parts' => [ '2025-6-5', false ];
		yield 'no separators' => [ '20250615', false ];
		yield 'another notation' => [ '15/06/2025', false ];
		yield 'a date carrying a time' => [ '2025-06-15T00:00:00Z', false ];
		yield 'a day the month does not have, which only format catches' => [ '2025-02-30', false ];
	}

	/**
	 * @dataProvider minPrecisionProvider
	 */
	public function testMinPrecisionNarrowsTheAcceptedForms( DatePrecision $minPrecision, string $value, bool $valid ): void {
		$items = TestProperty::buildDate( minPrecision: $minPrecision )->toJsonSchema()['items'];

		$this->assertSame( $valid, $this->jsonSchemaAccepts( $items, $value ) );
	}

	public static function minPrecisionProvider(): iterable {
		yield 'month admits a month' => [ DatePrecision::Month, '2025-06', true ];
		yield 'month admits a day' => [ DatePrecision::Month, '2025-06-15', true ];
		yield 'month rejects a year' => [ DatePrecision::Month, '2025', false ];
		yield 'day admits a day' => [ DatePrecision::Day, '2025-06-15', true ];
		yield 'day rejects a month' => [ DatePrecision::Day, '2025-06', false ];
	}

}
