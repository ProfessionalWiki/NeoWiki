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
