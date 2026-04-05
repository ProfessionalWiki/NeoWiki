<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty
 */
class SelectPropertyTest extends PropertyTestCase {

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "select",
	"description": "",
	"required": false,
	"default": null,
	"options": [],
	"multiple": false
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "select"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "select",
	"description": "Document status",
	"required": true,
	"default": null,
	"options": ["Draft", "Review", "Approved"],
	"multiple": false
}
JSON
		);
	}

	public function testFullSerializationWithDefaultValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "select",
	"description": "",
	"required": false,
	"default": null,
	"options": [],
	"multiple": false
}
JSON
		);
	}

	public function testMultiSelectSerialization(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "select",
	"description": "Tags",
	"required": false,
	"default": null,
	"options": ["Important", "Urgent", "Low priority"],
	"multiple": true
}
JSON
		);
	}

	public function testExceptionOnNonArrayOptions(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "select",
	"options": "not-an-array"
}
JSON
		);
	}

	public function testExceptionOnNonStringOption(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "select",
	"options": ["valid", 42]
}
JSON
		);
	}

	public function testExceptionOnInvalidMultiple(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "select",
	"multiple": "yes"
}
JSON
		);
	}

	public function testOptionsPreservesOrder(): void {
		$property = $this->fromJson(
			<<<JSON
{
	"type": "select",
	"options": ["Zebra", "Apple", "Mango"]
}
JSON
		);

		$this->assertSame( [ 'Zebra', 'Apple', 'Mango' ], $property->toJson()['options'] );
	}

}
