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

	public function testFullSerializationIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "select",
	"description": "Document status",
	"required": true,
	"default": null,
	"options": [
		{ "id": "opt1", "label": "Draft" },
		{ "id": "opt2", "label": "Review" },
		{ "id": "opt3", "label": "Approved" }
	],
	"multiple": false
}
JSON
		);
	}

	public function testExceptionOnLegacyStringOption(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "select",
	"options": ["Draft", "Review"]
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

	public function testExceptionOnDuplicateOptionId(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "select",
	"options": [
		{ "id": "dup", "label": "A" },
		{ "id": "dup", "label": "B" }
	]
}
JSON
		);
	}

	public function testExceptionOnDuplicateLabelCaseInsensitive(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "select",
	"options": [
		{ "id": "a", "label": "Draft" },
		{ "id": "b", "label": "draft" }
	]
}
JSON
		);
	}

	public function testExceptionOnDuplicateLabelWhitespace(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "select",
	"options": [
		{ "id": "a", "label": "Draft" },
		{ "id": "b", "label": "  Draft  " }
	]
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
	"options": [
		{ "id": "z", "label": "Zebra" },
		{ "id": "a", "label": "Apple" },
		{ "id": "m", "label": "Mango" }
	]
}
JSON
		);

		$ids = array_map( fn( $o ) => $o->getId(), $property->getOptions() );
		$this->assertSame( [ 'z', 'a', 'm' ], $ids );
	}

}
