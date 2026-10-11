<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;

/**
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
			targetSchema: SchemaReference::local( new SchemaName( 'Schema' ) ),
			multiple: true
		);

		$this->assertSame( 'foo', $property->getDescription() );
		$this->assertTrue( $property->isRequired() );
		$this->assertNull( $property->getDefault() );
		$this->assertSame( 'Schema', $property->getTargetSchema()->getText() );
		$this->assertTrue( $property->allowsMultipleValues() );
	}

	public function testTargetSchemaFromAnotherSourceRoundTrips(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "relation",
	"description": "",
	"required": false,
	"default": null,
	"targetSchema": "otherwiki:Company",
	"multiple": false
}
JSON
		);
	}

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "relation",
	"description": "",
	"required": false,
	"default": null,
	"targetSchema": "schema",
	"multiple": false
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "relation",
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
	"targetSchema": "schema",
	"multiple": false
}
JSON
		);
	}

	public function testObjectFormMultipleSeverityIsStable(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "relation",
	"description": "",
	"required": false,
	"default": null,
	"targetSchema": "schema",
	"multiple": { "value": false, "severity": "error" }
}
JSON
		);
	}

	public function testIgnoresALeftoverRelationName(): void {
		$this->assertArrayNotHasKey(
			'relation',
			$this->fromJson( '{ "type": "relation", "relation": "Works for", "targetSchema": "Company" }' )->toJson()
		);
	}

	public function testExceptionOnMissingTargetSchema(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson(
			<<<JSON
{
	"type": "relation"
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
	"targetSchema": ""
}
JSON
		);
	}

	public function testValueIsAnArrayOfTargetedRelations(): void {
		$this->assertEquals(
			[
				'type' => 'array',
				'items' => [
					'type' => 'object',
					'required' => [ 'target' ],
					'properties' => [
						'id' => [ 'type' => 'string' ],
						'target' => [ 'type' => 'string' ],
					],
				],
			],
			TestProperty::buildRelation( multiple: true )->toJsonSchema()
		);
	}

}
