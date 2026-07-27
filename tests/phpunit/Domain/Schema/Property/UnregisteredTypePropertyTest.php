<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty
 */
class UnregisteredTypePropertyTest extends PropertyTestCase {

	/**
	 * The type "color" is not among the core types, so it deserializes to an
	 * UnregisteredTypeProperty. Its object-form constraint severities must survive a
	 * round-trip (regression: the core `required` severity was previously dropped).
	 */
	public function testObjectFormRequiredSeverityRoundTripsForUnregisteredType(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "color",
	"description": "",
	"required": { "severity": "error" },
	"default": null,
	"allowedColors": [ "#ff0000" ]
}
JSON
		);
	}

	/**
	 * The severity on a type-specific key the core knows nothing about must survive too:
	 * the property is rebuilt from the normalized values, so re-serializing re-wraps the
	 * key once rather than wrapping the already-wrapped object again.
	 */
	public function testObjectFormCustomConstraintSeverityRoundTripsForUnregisteredType(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "color",
	"description": "",
	"required": false,
	"default": null,
	"allowedColors": { "value": [ "#ff0000" ], "severity": "error" }
}
JSON
		);
	}

	public function testUnannotatedUnregisteredTypeRoundTripsUnchanged(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "color",
	"description": "",
	"required": false,
	"default": null,
	"allowedColors": [ "#ff0000" ]
}
JSON
		);
	}

}
