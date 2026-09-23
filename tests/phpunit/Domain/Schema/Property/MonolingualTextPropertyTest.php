<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use ProfessionalWiki\NeoWiki\Domain\LanguageTag;

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

	public function testValueIsAnArrayOfTextsEachWithItsLanguage(): void {
		$this->assertSame(
			[
				'type' => 'array',
				'items' => [
					'type' => 'object',
					'required' => [ 'text', 'language' ],
					'properties' => [
						'text' => [ 'type' => 'string' ],
						'language' => [ 'type' => 'string', 'pattern' => LanguageTag::PATTERN ],
					],
				],
			],
			$this->fromJson( '{ "type": "monolingualText", "multiple": true }' )->toJsonSchema()
		);
	}

	public function testLengthConstraintsBoundEachText(): void {
		$value = $this->fromJson( '{ "type": "monolingualText", "minLength": 2, "maxLength": 40 }' )->toJsonSchema();

		$this->assertSame(
			[ 'type' => 'string', 'minLength' => 2, 'maxLength' => 40 ],
			$value['items']['properties']['text']
		);
	}

	public function testUniqueItemsConstraintForbidsDuplicateParts(): void {
		$value = $this->fromJson( '{ "type": "monolingualText", "multiple": true, "uniqueItems": true }' )->toJsonSchema();

		$this->assertTrue( $value['uniqueItems'] );
	}

}
