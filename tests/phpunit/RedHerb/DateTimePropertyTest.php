<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\RedHerb\DateTimeProperty;
use ProfessionalWiki\RedHerb\DateTimeType;

/**
 * @covers \ProfessionalWiki\RedHerb\DateTimeProperty
 */
class DateTimePropertyTest extends TestCase {

	public function testPropertyTypeIsDateTime(): void {
		$property = $this->buildProperty();

		$this->assertSame( 'dateTime', $property->getPropertyType() );
	}

	public function testMinimumAndMaximumAreNullByDefault(): void {
		$property = $this->buildProperty();

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

	private function buildProperty(): DateTimeProperty {
		return new DateTimeProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: null,
			maximum: null,
		);
	}

}
