<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Relation\RelationType
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty
 */
class RelationPropertyTest extends PropertyTestCase {

	public function testGetters(): void {
		$property = new RelationProperty(
			core: new PropertyCore(
				description: 'foo',
				required: true,
				default: null
			),
			relationType: new RelationType( 'Type' ),
			targetSchema: new SchemaName( 'Schema' ),
			multiple: true
		);

		$this->assertSame( 'foo', $property->getDescription() );
		$this->assertTrue( $property->isRequired() );
		$this->assertNull( $property->getDefault() );
		$this->assertSame( 'Type', $property->getRelationType()->getText() );
		$this->assertSame( 'Schema', $property->getTargetSchema()->getText() );
		$this->assertTrue( $property->allowsMultipleValues() );
	}

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "relation",
	"description": "",
	"required": false,
	"default": null,
	"relation": "type",
	"targetSchema": "schema",
	"multiple": false
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "relation",
	"relation": "type",
	"targetSchema": "schema"
}
JSON
			)
		);
	}

	public function testFullSerializationWithChangedValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "relation",
	"description": "foo",
	"required": true,
	"default": null,
	"relation": "type",
	"targetSchema": "schema",
	"multiple": true
}
JSON
		);
	}

	public function testFullSerializationWithDefaultValuesIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "relation",
	"description": "",
	"required": false,
	"default": null,
	"relation": "type",
	"targetSchema": "schema",
	"multiple": false
}
JSON
		);
	}

	public function testExceptionOnMissingRelationType(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "relation",
	"targetSchema": "schema"
}
JSON
		);
	}

	public function testExceptionOnMissingTargetSchema(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "relation",
	"relation": "type"
}
JSON
		);
	}

	public function testExceptionOnInvalidTargetSchema(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "relation",
	"relation": "type",
	"targetSchema": ""
}
JSON
		);
	}

}
