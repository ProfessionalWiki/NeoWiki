<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateProperty
 */
class DatePropertyTest extends TestCase {

	public function testPropertyTypeIsDate(): void {
		$property = $this->buildProperty();

		$this->assertSame( 'date', $property->getPropertyType() );
	}

	public function testMinimumAndMaximumAreNullByDefault(): void {
		$property = $this->buildProperty();

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

	private function buildProperty(): DateProperty {
		return new DateProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: null,
			maximum: null,
		);
	}

}
