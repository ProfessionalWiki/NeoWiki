<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DateTimeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType
 */
class DateTimeTypeTest extends TestCase {

	public function testTypeNameIsDateTime(): void {
		$this->assertSame( 'dateTime', ( new DateTimeType() )->getTypeName() );
	}

	public function testValueTypeIsString(): void {
		$this->assertSame( ValueType::String, ( new DateTimeType() )->getValueType() );
	}

	public function testHasNoDisplayAttributes(): void {
		$this->assertSame( [], ( new DateTimeType() )->getDisplayAttributeNames() );
	}

	public function testBuildPropertyDefinitionFromJsonReturnsDateTimeProperty(): void {
		$property = ( new DateTimeType() )->buildPropertyDefinitionFromJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'minimum' => '2020-01-01T00:00:00Z' ]
		);

		$this->assertInstanceOf( DateTimeProperty::class, $property );
		$this->assertSame( '2020-01-01T00:00:00Z', $property->getMinimum() );
	}

}
