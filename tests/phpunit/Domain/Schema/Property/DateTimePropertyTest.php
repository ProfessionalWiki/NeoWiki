<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\JsonSchemaAssertions;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty
 */
class DateTimePropertyTest extends TestCase {

	use JsonSchemaAssertions;

	public function testPropertyTypeIsDateTime(): void {
		$property = TestProperty::buildDateTime();

		$this->assertSame( 'dateTime', $property->getPropertyType() );
	}

	public function testMinimumAndMaximumAreNullByDefault(): void {
		$property = TestProperty::buildDateTime();

		$this->assertNull( $property->getMinimum() );
		$this->assertFalse( $property->hasMinimum() );
		$this->assertNull( $property->getMaximum() );
		$this->assertFalse( $property->hasMaximum() );
	}

	public function testMinimumAndMaximumFromJson(): void {
		$property = DateTimeProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'minimum' => '2020-01-01T00:00:00Z', 'maximum' => '2030-12-31T23:59:59Z' ]
		);

		$this->assertSame( '2020-01-01T00:00:00Z', $property->getMinimum() );
		$this->assertTrue( $property->hasMinimum() );
		$this->assertSame( '2030-12-31T23:59:59Z', $property->getMaximum() );
		$this->assertTrue( $property->hasMaximum() );
	}

	public function testSerializationRoundTrip(): void {
		$property = DateTimeProperty::fromPartialJson(
			new PropertyCore( description: 'A date', required: true, default: '2025-06-15T12:00:00Z' ),
			[ 'minimum' => '2020-01-01T00:00:00Z', 'maximum' => '2030-12-31T23:59:59Z' ]
		);

		$json = $property->toJson();

		$this->assertSame( 'dateTime', $json['type'] );
		$this->assertSame( 'A date', $json['description'] );
		$this->assertTrue( $json['required'] );
		$this->assertSame( '2025-06-15T12:00:00Z', $json['default'] );
		$this->assertSame( '2020-01-01T00:00:00Z', $json['minimum'] );
		$this->assertSame( '2030-12-31T23:59:59Z', $json['maximum'] );
	}

	public function testBuildPropertyDefinitionFromJsonViaType(): void {
		$type = new DateTimeType();
		$core = new PropertyCore( description: '', required: false, default: null );

		$property = $type->buildPropertyDefinitionFromJson( $core, [
			'minimum' => '2020-01-01T00:00:00Z',
		] );

		$this->assertInstanceOf( DateTimeProperty::class, $property );
		$this->assertSame( '2020-01-01T00:00:00Z', $property->getMinimum() );
		$this->assertNull( $property->getMaximum() );
	}

	public function testConstructorAcceptsOffsetWithColon(): void {
		$property = new DateTimeProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: '2020-01-01T00:00:00+02:00',
			maximum: '2030-12-31T23:59:59-05:30',
		);

		$this->assertSame( '2020-01-01T00:00:00+02:00', $property->getMinimum() );
		$this->assertSame( '2030-12-31T23:59:59-05:30', $property->getMaximum() );
	}

	public function testConstructorAcceptsFractionalSeconds(): void {
		$property = new DateTimeProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: '2020-01-01T00:00:00.123Z',
			maximum: null,
		);

		$this->assertSame( '2020-01-01T00:00:00.123Z', $property->getMinimum() );
	}

	public function testConstructorAcceptsNanosecondPrecision(): void {
		$property = new DateTimeProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: '2020-01-01T00:00:00.123456789Z',
			maximum: null,
		);

		$this->assertSame( '2020-01-01T00:00:00.123456789Z', $property->getMinimum() );
	}

	/**
	 * @dataProvider malformedDateTimeProvider
	 */
	public function testConstructorRejectsMalformedMinimum( string $malformed ): void {
		$this->expectException( \InvalidArgumentException::class );

		new DateTimeProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: $malformed,
			maximum: null,
		);
	}

	/**
	 * @dataProvider malformedDateTimeProvider
	 */
	public function testConstructorRejectsMalformedMaximum( string $malformed ): void {
		$this->expectException( \InvalidArgumentException::class );

		new DateTimeProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: null,
			maximum: $malformed,
		);
	}

	/**
	 * @dataProvider malformedDateTimeProvider
	 */
	public function testConstructorRejectsMalformedDefault( string $malformed ): void {
		$this->expectException( \InvalidArgumentException::class );

		new DateTimeProperty(
			core: new PropertyCore( description: '', required: false, default: $malformed ),
			minimum: null,
			maximum: null,
		);
	}

	/**
	 * @dataProvider malformedDateTimeProvider
	 */
	public function testFromPartialJsonRejectsMalformedBounds( string $malformed ): void {
		$this->expectException( \InvalidArgumentException::class );

		DateTimeProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'minimum' => $malformed ]
		);
	}

	public static function malformedDateTimeProvider(): iterable {
		yield 'year only' => [ '2025' ];
		yield 'year and month' => [ '2025-06' ];
		yield 'date only' => [ '2025-06-15' ];
		yield 'missing timezone' => [ '2025-06-15T12:00:00' ];
		yield 'invalid month' => [ '2025-13-01T00:00:00Z' ];
		yield 'invalid day' => [ '2025-02-30T00:00:00Z' ];
		yield 'garbage' => [ 'not-a-date' ];
		yield 'empty string' => [ '' ];
	}

	public function testParseStrictDateTimeReturnsTimestampForZOffset(): void {
		$result = DateTimeProperty::parseStrictDateTime( '2025-06-15T12:00:00Z' );

		$this->assertNotNull( $result );
	}

	public function testParseStrictDateTimeReturnsTimestampForExplicitOffset(): void {
		$result = DateTimeProperty::parseStrictDateTime( '2025-06-15T23:30:00+05:00' );

		$this->assertNotNull( $result );
	}

	public function testParseStrictDateTimeReturnsNullForCalendarOverflow(): void {
		$result = DateTimeProperty::parseStrictDateTime( '2025-02-30T00:00:00Z' );

		$this->assertNull( $result );
	}

	public function testParseStrictDateTimeReturnsNullForMissingOffset(): void {
		$result = DateTimeProperty::parseStrictDateTime( '2025-06-15T12:00:00' );

		$this->assertNull( $result );
	}

	public function testParseStrictDateTimeReturnsNullForGarbage(): void {
		$result = DateTimeProperty::parseStrictDateTime( 'not-a-date' );

		$this->assertNull( $result );
	}

	public function testValueIsOneFormattedString(): void {
		$this->assertSame(
			[
				'type' => 'array',
				'items' => [
					'type' => 'string',
					'format' => 'date-time',
					'pattern' => DateTimeProperty::ISO_DATE_TIME_PATTERN,
				],
				'maxItems' => 1,
			],
			TestProperty::buildDateTime()->toJsonSchema()
		);
	}

	public function testBoundsAreNotExpressed(): void {
		$value = TestProperty::buildDateTime(
			minimum: '2020-01-01T00:00:00Z',
			maximum: '2030-12-31T23:59:59Z'
		)->toJsonSchema();

		$this->assertArrayNotHasKey( 'minimum', $value, 'Standard JSON Schema cannot order dates.' );
		$this->assertArrayNotHasKey( 'maximum', $value, 'Standard JSON Schema cannot order dates.' );
		$this->assertArrayNotHasKey( 'minimum', $value['items'] );
		$this->assertArrayNotHasKey( 'maximum', $value['items'] );
	}

	/**
	 * @dataProvider dateTimePatternProvider
	 */
	public function testPatternAloneJudgesADateTime( string $value, bool $valid ): void {
		$this->assertSame( $valid, $this->jsonSchemaAccepts( $this->itemSchemaWithoutFormat(), $value ) );
	}

	public static function dateTimePatternProvider(): iterable {
		yield 'an ISO dateTime' => [ '2025-06-15T14:30:00Z', true ];
		yield 'an offset instead of Z' => [ '2025-06-15T14:30:00+02:00', true ];
		yield 'fractional seconds' => [ '2025-06-15T14:30:00.123456789Z', true ];
		yield 'ten fractional digits' => [ '2025-06-15T14:30:00.1234567890Z', false ];
		yield 'no timezone' => [ '2025-06-15T14:30:00', false ];
		yield 'an impossible hour' => [ '2025-06-15T25:00:00Z', false ];
		yield 'hour twenty-four' => [ '2025-06-15T24:00:00Z', false ];
		yield 'minute sixty' => [ '2025-06-15T14:60:00Z', false ];
		// ISO 8601 has a leap second; the wiki's own regex does not.
		yield 'second sixty' => [ '2025-06-15T14:30:60Z', false ];
		yield 'an offset hour of twenty-four' => [ '2025-06-15T14:30:00+24:00', false ];
		yield 'only a date' => [ '2025-06-15', false ];
	}

	/**
	 * The dialect leaves `format` an annotation, so `pattern` is all a validator that does not
	 * assert formats has to go on.
	 *
	 * @return array<string, mixed>
	 */
	private function itemSchemaWithoutFormat(): array {
		$items = TestProperty::buildDateTime()->toJsonSchema()['items'];
		unset( $items['format'] );

		return $items;
	}

}
