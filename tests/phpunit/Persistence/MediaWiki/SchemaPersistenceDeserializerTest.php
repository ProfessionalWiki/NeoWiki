<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyTypeRegistry;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty
 */
class SchemaPersistenceDeserializerTest extends TestCase {

	private const SCHEMA_JSON = <<<'JSON'
		{
		    "description": "Test Schema",
		    "propertyDefinitions": {
		        "Name": {
		            "type": "text",
		            "description": "",
		            "required": true,
		            "default": null,
		            "multiple": false,
		            "uniqueItems": false,
		            "minLength": null,
		            "maxLength": null
		        },
		        "Swatch": {
		            "type": "color",
		            "description": "The brand color",
		            "required": false,
		            "default": "#ff5733",
		            "allowedColors": [ "#ff5733", "#33ff57" ]
		        }
		    }
		}
		JSON;

	public function testKeepsPropertyOfUnregisteredType(): void {
		$schema = $this->deserialize();

		$this->assertTrue( $schema->hasProperty( 'Swatch' ) );
		$this->assertInstanceOf( UnregisteredTypeProperty::class, $schema->getProperty( 'Swatch' ) );
		$this->assertSame( 'color', $schema->getProperty( 'Swatch' )->getPropertyType() );
	}

	public function testKeepsPropertiesOfRegisteredTypes(): void {
		$this->assertInstanceOf( TextProperty::class, $this->deserialize()->getProperty( 'Name' ) );
	}

	public function testKeepsCoreFieldsOfUnregisteredType(): void {
		$property = $this->deserialize()->getProperty( 'Swatch' );

		$this->assertSame( 'The brand color', $property->getDescription() );
		$this->assertFalse( $property->isRequired() );
		$this->assertSame( '#ff5733', $property->getDefault() );
	}

	public function testKeepsTypeSpecificFieldsOfUnregisteredType(): void {
		$this->assertSame(
			[ 'allowedColors' => [ '#ff5733', '#33ff57' ] ],
			$this->deserialize()->getProperty( 'Swatch' )->nonCoreToJson()
		);
	}

	/**
	 * Guards against a re-save dropping the type-specific fields of a property whose
	 * type is not registered. The Schema is served to editors via this serializer.
	 */
	public function testReserializationPreservesUnregisteredTypeProperty(): void {
		$this->assertJsonStringEqualsJsonString(
			self::SCHEMA_JSON,
			( new SchemaPresentationSerializer() )->serialize( $this->deserialize() )
		);
	}

	public function testSkipsStructurallyInvalidPropertyOfRegisteredType(): void {
		$json = '{"propertyDefinitions": {"Age": {"type": "boolean", "default": "not a boolean"}}}';

		$this->assertFalse( $this->deserialize( $json )->hasProperty( 'Age' ) );
	}

	/**
	 * @dataProvider propertyWithoutUsableTypeProvider
	 */
	public function testSkipsPropertyWithoutAUsableType( string $propertyJson ): void {
		$json = '{"propertyDefinitions": {"Broken": ' . $propertyJson . ', "Name": {"type": "text"}}}';

		$schema = $this->deserialize( $json );

		$this->assertFalse( $schema->hasProperty( 'Broken' ) );
		$this->assertTrue( $schema->hasProperty( 'Name' ) );
	}

	public function propertyWithoutUsableTypeProvider(): iterable {
		yield 'no type key' => [ '{"description": "no type"}' ];
		yield 'null type' => [ '{"type": null}' ];
		yield 'non-string type' => [ '{"type": 42}' ];
		yield 'empty type' => [ '{"type": ""}' ];
	}

	private function deserialize( string $json = self::SCHEMA_JSON ): Schema {
		return ( new SchemaPersistenceDeserializer( PropertyTypeRegistry::withCoreTypes() ) )
			->deserialize( new SchemaName( 'TestSchema' ), $json );
	}

}
