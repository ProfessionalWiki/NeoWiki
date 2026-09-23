<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyCore;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Presentation\JsonSchemaSerializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSchema;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;
use stdClass;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\JsonSchemaSerializer
 */
class JsonSchemaSerializerTest extends TestCase {

	private const string DOCUMENT_URL = 'https://example.com/w/rest.php/neowiki/v0/schema/Company/json-schema';

	/**
	 * Nothing a built-in Property Definition emits, so that finding it proves it came from the definition.
	 */
	private const array STUB_VALUE_SCHEMA = [ 'type' => 'string', 'const' => 'stated by the definition itself' ];

	public function testDeclaresTheDraft202012Dialect(): void {
		$document = $this->documentFor( $this->schemaWith( [] ) );

		$this->assertSame( 'https://json-schema.org/draft/2020-12/schema', $document['$schema'] );
	}

	public function testIdIsTheUrlTheDocumentIsServedFrom(): void {
		$document = $this->documentFor( $this->schemaWith( [] ) );

		$this->assertSame( self::DOCUMENT_URL, $document['$id'] );
	}

	public function testTitleIsTheSchemaName(): void {
		$document = $this->documentFor( $this->schemaWith( [], name: 'Museum' ) );

		$this->assertSame( 'Museum', $document['title'] );
	}

	public function testDescriptionIsTheSchemaDescription(): void {
		$document = $this->documentFor( $this->schemaWith( [], description: 'A business entity' ) );

		$this->assertSame( 'A business entity', $document['description'] );
	}

	public function testDescriptionIsOmittedWhenTheSchemaHasNone(): void {
		$document = $this->documentFor( $this->schemaWith( [], description: '' ) );

		$this->assertArrayNotHasKey( 'description', $document );
	}

	public function testSubjectIsAnObjectNeedingOnlyStatements(): void {
		// The replace body carries no `schema`; the const still pins it when present.
		$document = $this->documentFor( $this->schemaWith( [] ) );

		$this->assertSame( 'object', $document['type'] );
		$this->assertSame( [ 'statements' ], $document['required'] );
	}

	public function testSubjectCarriesFieldsBeyondTheDescribedOnes(): void {
		$document = $this->documentFor( $this->schemaWith( [] ) );

		$this->assertArrayNotHasKey(
			'additionalProperties',
			$document,
			'Write bodies also carry comment, pageTitle and id; read bodies id, displayName and page fields.'
		);
	}

	public function testSchemaFieldIsPinnedToTheSchemaName(): void {
		$document = $this->documentFor( $this->schemaWith( [], name: 'Museum' ) );

		$this->assertSame( [ 'const' => 'Museum' ], $document['properties']['schema'] );
	}

	public function testLabelIsAStringOrNull(): void {
		$document = $this->documentFor( $this->schemaWith( [] ) );

		$this->assertSame( [ 'type' => [ 'string', 'null' ] ], $document['properties']['label'] );
	}

	public function testStatementsRejectUndeclaredProperties(): void {
		$statements = $this->documentFor( $this->schemaWith( [] ) )['properties']['statements'];

		$this->assertSame( 'object', $statements['type'] );
		$this->assertFalse( $statements['additionalProperties'] );
	}

	public function testRequiredPropertiesAreListedUnderStatements(): void {
		$schema = $this->schemaWith( [
			'Optional' => TestProperty::buildText(),
			'Needed' => TestProperty::buildText( required: true ),
			'Also optional' => TestProperty::buildText(),
		] );

		$this->assertSame(
			[ 'Needed' ],
			$this->documentFor( $schema )['properties']['statements']['required']
		);
	}

	public function testSchemaWithoutPropertiesDescribesAnEmptyStatementMap(): void {
		$document = json_decode( $this->newSerializer()->serialize( $this->schemaWith( [] ) ) );

		$this->assertEquals( new stdClass(), $document->properties->statements->properties );
	}

	public function testStatementNeedsItsTypeAndValue(): void {
		$statement = $this->statementSchemaFor( TestProperty::buildText() );

		$this->assertSame( 'object', $statement['type'] );
		$this->assertSame( [ 'propertyType', 'value' ], $statement['required'] );
		$this->assertSame( [ 'const' => 'text' ], $statement['properties']['propertyType'] );
	}

	public function testStatementCarriesThePropertyDescription(): void {
		$statement = $this->statementSchemaFor( TestProperty::buildText( description: 'The trading name' ) );

		$this->assertSame( 'The trading name', $statement['description'] );
	}

	public function testStatementDescriptionIsOmittedWhenThePropertyHasNone(): void {
		$statement = $this->statementSchemaFor( TestProperty::buildText( description: '' ) );

		$this->assertArrayNotHasKey( 'description', $statement );
	}

	public function testValueSchemaIsTheOneItsDefinitionStates(): void {
		$this->assertSame(
			self::STUB_VALUE_SCHEMA,
			$this->valueSchemaFor( $this->definitionStating( self::STUB_VALUE_SCHEMA ) )
		);
	}

	public function testValueOfAnUnregisteredTypeIsUnconstrained(): void {
		$this->assertTrue( $this->valueSchemaFor( $this->propertyOfUnregisteredType( 'gone' ) ) );
	}

	public function testUnregisteredTypeIsStillNamedOnItsStatement(): void {
		$this->assertSame(
			[ 'const' => 'gone' ],
			$this->statementSchemaFor( $this->propertyOfUnregisteredType( 'gone' ) )['properties']['propertyType']
		);
	}

	public function testDocumentIsPrettyPrintedWithReadableUrls(): void {
		$json = $this->newSerializer()->serialize( $this->schemaWith( [] ) );

		$this->assertStringContainsString( '"$id": "' . self::DOCUMENT_URL . '"', $json );
	}

	public function testNonAsciiTextIsLeftUnescaped(): void {
		$json = $this->newSerializer()->serialize( $this->schemaWith( [], description: 'Café' ) );

		$this->assertStringContainsString( 'Café', $json );
	}

	private function newSerializer(): JsonSchemaSerializer {
		return new JsonSchemaSerializer( documentUrl: self::DOCUMENT_URL );
	}

	/**
	 * @return array<string, mixed>
	 */
	private function documentFor( Schema $schema ): array {
		return json_decode( $this->newSerializer()->serialize( $schema ), true );
	}

	/**
	 * @param array<string, PropertyDefinition> $properties
	 */
	private function schemaWith( array $properties, string $name = 'Company', string $description = '' ): Schema {
		return TestSchema::build(
			name: new SchemaName( $name ),
			description: $description,
			properties: new PropertyDefinitions( $properties ),
		);
	}

	/**
	 * @return array<string, mixed>
	 */
	private function statementSchemaFor( PropertyDefinition $definition ): array {
		return $this->documentFor( $this->schemaWith( [ 'Field' => $definition ] ) )
			['properties']['statements']['properties']['Field'];
	}

	private function valueSchemaFor( PropertyDefinition $definition ): mixed {
		return $this->statementSchemaFor( $definition )['properties']['value'];
	}

	/**
	 * @param array<string, mixed> $valueSchema
	 */
	private function definitionStating( array $valueSchema ): PropertyDefinition {
		return new class( $valueSchema ) extends PropertyDefinition {

			/**
			 * @param array<string, mixed> $valueSchema
			 */
			public function __construct(
				private readonly array $valueSchema
			) {
				parent::__construct( new PropertyCore( description: '', required: false, default: null ) );
			}

			public function getPropertyType(): string {
				return 'stub';
			}

			public function nonCoreToJson(): array {
				return [];
			}

			public function toJsonSchema(): array {
				return $this->valueSchema;
			}

		};
	}

	/**
	 * An UnregisteredTypeProperty, which is what a definition of any type beyond the core ones
	 * deserializes to.
	 */
	private function propertyOfUnregisteredType( string $type ): PropertyDefinition {
		return PropertyDefinition::fromJson(
			[ 'type' => $type ],
			TestSources::newPropertyTypeRegistry()
		);
	}

}
