<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use InvalidArgumentException;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\TextProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\UnregisteredTypeProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Presentation\SchemaPresentationSerializer;
use Psr\Log\LoggerInterface;
use Psr\Log\LogLevel;
use Psr\Log\NullLogger;
use Psr\Log\Test\TestLogger;

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

	public function testSkipsPropertyWhoseNameIsAnInteger(): void {
		$schema = $this->deserialize( '{"propertyDefinitions": {"2024": {"type": "text"}, "Name": {"type": "text"}}}' );

		$this->assertFalse( $schema->hasProperty( '2024' ) );
		$this->assertTrue( $schema->hasProperty( 'Name' ) );
	}

	public function testReadsTheDeclarationThatTheSubjectsAreDependent(): void {
		$json = '{"subjectsAreDependent": true, "propertyDefinitions": {}}';

		$this->assertTrue( $this->deserialize( $json )->subjectsAreDependent() );
	}

	/**
	 * @dataProvider standaloneDeclarationProvider
	 */
	public function testSubjectsAreStandaloneUnlessTheSchemaDeclaresThemDependent( string $json ): void {
		$this->assertFalse( $this->deserialize( $json )->subjectsAreDependent() );
	}

	public function standaloneDeclarationProvider(): iterable {
		yield 'absent' => [ '{"propertyDefinitions": {}}' ];
		yield 'false' => [ '{"subjectsAreDependent": false, "propertyDefinitions": {}}' ];
		yield 'not a boolean' => [ '{"subjectsAreDependent": "yes", "propertyDefinitions": {}}' ];
	}

	public function testReadsLabelsTrimmedUnderLowercaseTagsWithoutEmptyOnes(): void {
		$json = '{"labels": {"zh-Hant": " 公司 ", "de": "  ", "nl": 42}, "propertyDefinitions": {}}';

		$this->assertSame( [ 'zh-hant' => '公司' ], $this->deserialize( $json )->getLabels() );
	}

	public function testLogsAPropertyDefinitionItCannotRead(): void {
		$logger = new TestLogger();

		$this->deserialize( '{"propertyDefinitions": {"Owner": {"type": "relation"}}}', $logger );

		$context = $this->contextOfTheOnlyWarning( $logger );
		$this->assertSame( 'Owner', $context['property'] );
		$this->assertSame( 'TestSchema', $context['schema'] );
		$this->assertStringContainsString( 'target Schemas of a relation property', $context['message'] );
		$this->assertInstanceOf( InvalidArgumentException::class, $context['exception'] );
	}

	public function testLogsAPropertyWhoseNameIsAnInteger(): void {
		$logger = new TestLogger();

		$this->deserialize( '{"propertyDefinitions": {"2024": {"type": "text"}}}', $logger );

		$context = $this->contextOfTheOnlyWarning( $logger );
		$this->assertSame( '2024', $context['property'] );
		$this->assertSame( 'TestSchema', $context['schema'] );
	}

	public function testLogsNothingForPropertiesItCanRead(): void {
		$logger = new TestLogger();

		$this->deserialize( logger: $logger );

		$this->assertSame( [], $logger->records );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function contextOfTheOnlyWarning( TestLogger $logger ): array {
		$this->assertCount( 1, $logger->records );
		$this->assertSame( LogLevel::WARNING, $logger->records[0]['level'] );

		return $logger->records[0]['context'];
	}

	private function deserialize( string $json = self::SCHEMA_JSON, LoggerInterface $logger = new NullLogger() ): Schema {
		return ( new SchemaPersistenceDeserializer( TestSources::newPropertyTypeRegistry(), $logger ) )
			->deserialize( new SchemaName( 'TestSchema' ), $json );
	}

}
