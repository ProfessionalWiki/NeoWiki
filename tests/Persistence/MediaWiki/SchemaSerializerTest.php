<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\BooleanProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\NumberProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\Value\ValueType;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\CheckboxFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\NumberFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\TextFormat;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaDeserializer;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;

class SchemaSerializerTest extends TestCase {

	private SchemaSerializer $serializer;

	protected function setUp(): void {
		$this->serializer = new SchemaSerializer();
	}

	private function assertSerializesTo( string $expectedJson, Schema $schema ): void {
		$json = $this->serializer->serialize( $schema );
		$this->assertJsonStringEqualsJsonString( $expectedJson, $json );
	}

	public function testSerializeBoolean(): void {
		$schema = new Schema(
			name: new SchemaName( 'testSchema' ),
			description: 'Test schema description',
			properties: new PropertyDefinitions( [
				'testBoolean' => new BooleanProperty(
					format: CheckboxFormat::NAME,
					description: 'Test boolean property',
					required: true,
					default: false
				)
			] )
		);

		$expectedJson = json_encode( [
			'description' => 'Test schema description',
			'propertyDefinitions' => [
				'testBoolean' => [
					'type' => ValueType::Boolean,
					'description' => 'Test boolean property',
					'required' => true,
					'default' => false,
					'format' => CheckboxFormat::NAME,
					'multiple' => false
				]
			]
		] );

		$this->assertSerializesTo( $expectedJson, $schema );
	}

	public function testSerializeNumber(): void {
		$schema = new Schema(
			name: new SchemaName( 'testSchema' ),
			description: 'Test schema description',
			properties: new PropertyDefinitions( [
				'testNumber' => new NumberProperty(
					format: NumberFormat::NAME,
					description: 'Test number property',
					required: false,
					default: 0,
					minimum: null,
					maximum: null
				)
			] )
		);

		$expectedJson = json_encode( [
			'description' => 'Test schema description',
			'propertyDefinitions' => [
				'testNumber' => [
					'type' => ValueType::Number,
					'description' => 'Test number property',
					'required' => false,
					'default' => 0,
					'format' => NumberFormat::NAME,
					'multiple' => false
				]
			]
		] );

		$this->assertSerializesTo( $expectedJson, $schema );
	}

	public function testSerializeRelation(): void {
		$schema = new Schema(
			name: new SchemaName( 'testSchema' ),
			description: 'Test schema description',
			properties: new PropertyDefinitions( [
				'testRelation' => new RelationProperty(
					description: 'Test relation property',
					required: false,
					default: null,
					relationType: new RelationType( 'testRelationType' ),
					targetSchema: new SchemaName( 'targetSchema' ),
					multiple: false
				)
			] )
		);

		$expectedJson = json_encode( [
			'description' => 'Test schema description',
			'propertyDefinitions' => [
				'testRelation' => [
					'type' => 'relation',
					'description' => 'Test relation property',
					'required' => false,
					'default' => null,
					'relation' => 'testRelationType',
					'targetSchema' => 'targetSchema',
					'multiple' => false,
					'format' => 'relation'
				]
			]
		] );

		$this->assertSerializesTo( $expectedJson, $schema );
	}

	public function testSerializeString(): void {
		$schema = new Schema(
			name: new SchemaName( 'testSchema' ),
			description: 'Test schema description',
			properties: new PropertyDefinitions( [
				'testString' => new StringProperty(
					format: TextFormat::NAME,
					description: 'Test string property',
					required: true,
					default: 'default',
					multiple: false
				)
			] )
		);

		$expectedJson = json_encode( [
			'description' => 'Test schema description',
			'propertyDefinitions' => [
				'testString' => [
					'type' => ValueType::String,
					'description' => 'Test string property',
					'required' => true,
					'default' => 'default',
					'format' => 'text',
					'multiple' => false
				]
			]
		] );

		$this->assertSerializesTo( $expectedJson, $schema );
	}

	/**
	 * @dataProvider exampleSchemaProvider
	 */
	public function testSchemaIntegrityAfterRoundTripSerialization( string $schemaName, string $originalSchemaJson ): void {
		$originalSchemaArray = json_decode( $originalSchemaJson, true );

		$deserializer = new SchemaDeserializer();
		$originalSchema = $deserializer->deserialize( new SchemaName( $schemaName ), json_encode( $originalSchemaArray ) );

		$serialized = $this->serializer->serialize( $originalSchema );

		$deserialized = $deserializer->deserialize( $originalSchema->getName(), $serialized );

		$this->assertEquals( $originalSchema, $deserialized );
	}

	public function exampleSchemaProvider(): iterable {
		$dir = new \DirectoryIterator( __DIR__ . '/../../../DemoData/Schema' );

		foreach ( $dir as $fileinfo ) {
			if ( !$fileinfo->isDot() && $fileinfo->getExtension() === 'json' ) {
				yield [ $fileinfo->getBasename( '.json' ), TestData::getFileContents( 'Schema/' . $fileinfo->getFilename() ) ];
			}
		}
	}

}
