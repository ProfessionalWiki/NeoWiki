<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty
 */
class NumberPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "number",
	"description": "",
	"required": false,
	"default": null,
	"precision": null,
	"minimum": null,
	"maximum": null
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "number"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "number",
	"description": "foo",
	"required": true,
	"default": 42,
	"precision": 2,
	"minimum": 0,
	"maximum": 100
}
JSON
		);
	}

	public function testFullSerializationWithDefaultValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "number",
	"description": "",
	"required": false,
	"default": null,
	"precision": null,
	"minimum": null,
	"maximum": null
}
JSON
		);
	}

	public function testExceptionOnInvalidPrecision(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "number",
	"precision": "yes"
}
JSON
		);
	}

	public function testExceptionOnInvalidMax(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "number",
	"maximum": "yes"
}
JSON
		);
	}

	public function testExceptionOnInvalidMin(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "number",
	"minimum": "yes"
}
JSON
		);
	}

	public function testMaximumWithErrorSeverityRoundTrips(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "number",
	"description": "",
	"required": false,
	"default": null,
	"precision": null,
	"minimum": null,
	"maximum": { "value": 100, "severity": "error" }
}
JSON
		);
	}

	public function testRequiredWithErrorSeverityRoundTrips(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "number",
	"description": "",
	"required": { "severity": "error" },
	"default": null,
	"precision": null,
	"minimum": null,
	"maximum": null
}
JSON
		);
	}

	public function testExplicitWarningSeverityCanonicalizesToShorthand(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "number",
	"description": "",
	"required": false,
	"default": null,
	"precision": null,
	"minimum": null,
	"maximum": 100
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "number",
	"maximum": { "value": 100, "severity": "warning" }
}
JSON
			)
		);
	}

	/**
	 * precision is a Display Attribute, not a Constraint, so a severity on it is meaningless.
	 * It must be dropped rather than re-emitted, which would turn precision into an object
	 * and break every consumer that reads it as a scalar.
	 */
	public function testSeverityOnDisplayAttributeIsDroppedRatherThanReserialized(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "number",
	"description": "",
	"required": false,
	"default": null,
	"precision": 2,
	"minimum": null,
	"maximum": null
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "number",
	"precision": { "value": 2, "severity": "error" }
}
JSON
			)
		);
	}

}
