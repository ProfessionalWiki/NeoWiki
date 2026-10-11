<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaReference;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSubjectIds;

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
			targetSchemas: [ SchemaReference::local( new SchemaName( 'Schema' ) ) ],
			multiple: true
		);

		$this->assertSame( 'foo', $property->getDescription() );
		$this->assertTrue( $property->isRequired() );
		$this->assertNull( $property->getDefault() );
		$this->assertTrue( $property->allowsMultipleValues() );
	}

	public function testAcceptsTargetsOfEachListedSchema(): void {
		$property = TestProperty::buildRelation( targetSchemas: [ 'Person', 'Group', 'Organization' ] );

		$this->assertTrue( $property->hasTargetSchema( TestSchema::reference( 'Person' ) ) );
		$this->assertTrue( $property->hasTargetSchema( TestSchema::reference( 'Group' ) ) );
		$this->assertTrue( $property->hasTargetSchema( TestSchema::reference( 'Organization' ) ) );
	}

	public function testDoesNotAcceptTargetsOfAnUnlistedSchema(): void {
		$property = TestProperty::buildRelation( targetSchemas: [ 'Person', 'Group' ] );

		$this->assertFalse( $property->hasTargetSchema( TestSchema::reference( 'Company' ) ) );
	}

	public function testDoesNotAcceptTargetsOfALocalNamesakeOfASchemaFromAnotherSource(): void {
		$property = TestProperty::buildRelation(
			targetSchemas: [ SchemaReference::sourced( TestSubjectIds::OTHER_SOURCE_KEY, new SchemaName( 'Company' ) ) ]
		);

		$this->assertFalse( $property->hasTargetSchema( TestSchema::reference( 'Company' ) ) );
	}

	public function testTargetSchemasOfEitherSourceRoundTrip(): void {
		$this->assertSerializationDoesNotChange(
			<<<JSON
{
	"type": "relation",
	"description": "",
	"required": false,
	"default": null,
	"targetSchemas": [ "Person", { "source": "otherwiki", "name": "Company" }, "Group" ],
	"multiple": false
}
JSON
		);
	}

	public function testKeepsTheFirstOfTargetSchemasThatNameTheSameSchema(): void {
		$property = RelationProperty::fromPartialJson(
			new PropertyCore( description: '', required: false, default: null ),
			[ 'targetSchemas' => [ 'Person', 'City', 'person' ] ],
			TestSources::newSchemaReferenceParser( [ 'person' => 'Person' ] )
		);

		$this->assertSame( [ 'Person', 'City' ], $property->toJson()['targetSchemas'] );
	}

	public function testMinimalSerialization(): void {
		$this->assertJsonStringEqualsJsonString(
			<<<JSON
{
	"type": "relation",
	"description": "",
	"required": false,
	"default": null,
	"targetSchemas": [ "schema" ],
	"multiple": false
}
JSON,
			$this->deserializeAndReserialize(
				<<<JSON
{
	"type": "relation",
	"targetSchemas": [ "schema" ]
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
	"targetSchemas": [ "schema" ],
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
	"targetSchemas": [ "schema" ],
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
	"targetSchemas": [ "schema" ],
	"multiple": { "value": false, "severity": "error" }
}
JSON
		);
	}

	public function testExceptionOnTargetSchemaInPlaceOfTargetSchemas(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson( '{ "type": "relation", "targetSchema": "Person" }' );
	}

	public function testExceptionOnEmptyTargetSchemas(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson( '{ "type": "relation", "targetSchemas": [] }' );
	}

	public function testExceptionOnTargetSchemasThatAreNoList(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson( '{ "type": "relation", "targetSchemas": "Person" }' );
	}

	public function testExceptionOnOneReferenceObjectInPlaceOfAList(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson( '{ "type": "relation", "targetSchemas": { "source": "otherwiki", "name": "Person" } }' );
	}

	public function testExceptionOnAnInvalidTargetSchema(): void {
		$this->expectException( InvalidArgumentException::class );
		$this->fromJson( '{ "type": "relation", "targetSchemas": [ "Person", "" ] }' );
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
