<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\RedHerb;

use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property\PropertyTestCase;
use ProfessionalWiki\RedHerb\DateTimeProperty;
use ProfessionalWiki\RedHerb\DateTimeType;
use TypeError;

/**
 * @covers \ProfessionalWiki\RedHerb\DateTimeProperty
 * @covers \ProfessionalWiki\RedHerb\DateTimeType
 */
class DateTimePropertyTest extends PropertyTestCase {

	protected function fromJson( string $json ): PropertyDefinition {
		$registry = PropertyTypeRegistry::withCoreTypes();
		$registry->registerType( new DateTimeType() );

		return PropertyDefinition::fromJson(
			json_decode( $json, true ),
			$registry
		);
	}

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "datetime",
	"description": "",
	"required": false,
	"default": null,
	"minimum": null,
	"maximum": null
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "datetime"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "datetime",
	"description": "A date",
	"required": true,
	"default": "2025-06-15T12:00:00Z",
	"minimum": "2020-01-01T00:00:00Z",
	"maximum": "2030-12-31T23:59:59Z"
}
JSON
		);
	}

	public function testFullSerializationWithDefaultValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "datetime",
	"description": "",
	"required": false,
	"default": null,
	"minimum": null,
	"maximum": null
}
JSON
		);
	}

	public function testExceptionOnInvalidMinimum(): void {
		$this->expectException( TypeError::class );

		new DateTimeProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			// @phpstan-ignore-next-line
			minimum: 42,
			maximum: null,
		);
	}

	public function testExceptionOnInvalidMaximum(): void {
		$this->expectException( TypeError::class );

		new DateTimeProperty(
			core: new PropertyCore( description: '', required: false, default: null ),
			minimum: null,
			// @phpstan-ignore-next-line
			maximum: true,
		);
	}

}
