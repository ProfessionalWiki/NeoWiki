<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\CheckboxFormat;
use ProfessionalWiki\NeoWiki\Domain\ValueFormat\Formats\NumberFormat;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer
 */
class SchemaPresentationSerializerTest extends TestCase {

	private SchemaPresentationSerializer $serializer;

	protected function setUp(): void {
		$this->serializer = NeoWikiExtension::getInstance()->getPersistenceSchemaSerializer();
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
				'testBoolean' => TestProperty::buildCheckbox(
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
					'description' => 'Test boolean property',
					'required' => true,
					'default' => false,
					'format' => CheckboxFormat::NAME,
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
				'testNumber' => TestProperty::buildNumber(
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
					'description' => 'Test number property',
					'required' => false,
					'default' => 0,
					'format' => NumberFormat::NAME,
					'maximum' => null,
					'minimum' => null,
					'precision' => null,
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
				'testRelation' => TestProperty::buildRelation(
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
				'testString' => TestProperty::buildText(
					description: 'Test string property',
					required: true,
					default: 'default',
					multiple: false,
				)
			] )
		);

		$expectedJson = json_encode( [
			'description' => 'Test schema description',
			'propertyDefinitions' => [
				'testString' => [
					'description' => 'Test string property',
					'required' => true,
					'default' => 'default',
					'format' => 'text',
					'multiple' => false,
					'uniqueItems' => false,
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

		$deserializer = new SchemaPersistenceDeserializer( NeoWikiExtension::getInstance()->getValueFormatLookup() );
		$originalSchema = $deserializer->deserialize( new SchemaName( $schemaName ), json_encode( $originalSchemaArray ) );

		$serialized = $this->serializer->serialize( $originalSchema );

		$deserialized = $deserializer->deserialize( $originalSchema->getName(), $serialized );

		$this->assertEquals( $originalSchema, $deserialized );
	}

	public function exampleSchemaProvider(): iterable {
		$dir = new \DirectoryIterator( __DIR__ . '/../../DemoData/Schema' );

		foreach ( $dir as $fileinfo ) {
			if ( !$fileinfo->isDot() && $fileinfo->getExtension() === 'json' ) {
				yield [ $fileinfo->getBasename( '.json' ), TestData::getFileContents( 'Schema/' . $fileinfo->getFilename() ) ];
			}
		}
	}

}
