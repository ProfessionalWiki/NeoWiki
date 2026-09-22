<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\MonolingualTextProperty
 */
class MonolingualTextPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "monolingualText",
	"description": "",
	"required": false,
	"default": null,
	"multiple": false,
	"uniqueItems": false,
	"minLength": null,
	"maxLength": null
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "monolingualText"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "monolingualText",
	"description": "The title in its original language",
	"required": true,
	"default": [ { "text": "Zinema", "language": "eu" } ],
	"multiple": true,
	"uniqueItems": true,
	"minLength": 2,
	"maxLength": 40
}
JSON
		);
	}

}
