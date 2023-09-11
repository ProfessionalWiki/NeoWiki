<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\CheckboxProperty
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition
 */
class CheckboxPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"format": "checkbox",
	"description": "",
	"required": false,
	"default": null
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"format": "checkbox"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"format": "checkbox",
	"description": "foo",
	"required": true,
	"default": true
}
JSON
		);
	}

	public function testFullSerializationWithDefaultValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"format": "checkbox",
	"description": "",
	"required": false,
	"default": null
}
JSON
		);
	}

	public function testHasDefault(): void {
		$this->assertFalse( TestProperty::buildCheckbox( default: null )->hasDefault() );
		$this->assertTrue( TestProperty::buildCheckbox( default: false )->hasDefault() );
	}

}
