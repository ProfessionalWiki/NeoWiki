<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
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
		);

		$this->assertSame( '2024-02-29', $property->getMinimum() );
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
		yield 'year only' => [ '2025' ];
		yield 'year and month' => [ '2025-06' ];
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
					'format' => 'date',
					'pattern' => DateProperty::ISO_DATE_PATTERN,
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
		$this->assertSame( $valid, $this->jsonSchemaAccepts( $this->itemSchemaWithoutFormat(), $value ) );
	}

	public static function datePatternProvider(): iterable {
		yield 'an ISO date' => [ '2025-06-15', true ];
		yield 'a year before year one' => [ '-0500-06-15', true ];
		yield 'an impossible month' => [ '2025-13-01', false ];
		yield 'month zero' => [ '2025-00-15', false ];
		yield 'an impossible day' => [ '2025-06-32', false ];
		yield 'day zero' => [ '2025-06-00', false ];
		yield 'unpadded parts' => [ '2025-6-5', false ];
		yield 'no separators' => [ '20250615', false ];
		yield 'another notation' => [ '15/06/2025', false ];
		yield 'a date carrying a time' => [ '2025-06-15T00:00:00Z', false ];
	}

	/**
	 * The dialect leaves `format` an annotation, so `pattern` is all a validator that does not
	 * assert formats has to go on.
	 *
	 * @return array<string, mixed>
	 */
	private function itemSchemaWithoutFormat(): array {
		$items = TestProperty::buildDate()->toJsonSchema()['items'];
		unset( $items['format'] );

		return $items;
	}

}
