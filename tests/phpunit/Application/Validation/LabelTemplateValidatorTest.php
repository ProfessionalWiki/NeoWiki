<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application\Validation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\Validation\LabelTemplateValidator;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaPersistenceDeserializer;
use ProfessionalWiki\NeoWiki\Tests\Data\TestSources;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\Validation\LabelTemplateValidator
 */
class LabelTemplateValidatorTest extends TestCase {

	private const string PROPERTIES = '{
		"Title": { "type": "text" },
		"Caption": { "type": "monolingualText" },
		"Year": { "type": "number" },
		"Status": { "type": "select", "options": [ { "id": "o1", "label": "Lost" } ] },
		"Creator": { "type": "relation", "relation": "Created by", "targetSchema": "Person" },
		"Framed": { "type": "boolean" },
		"Swatch": { "type": "color" }
	}';

	public function testTemplateNamingPropertiesThatHoldTextOrNumbersIsValid(): void {
		$this->assertSame( [], $this->errorsFor( '{Title} ({Year}, {Status})' ) );
	}

	/**
	 * @return string[]
	 */
	private function errorsFor( ?string $labelTemplate ): array {
		$json = '{' . ( $labelTemplate === null ? '' : '"labelTemplate": ' . json_encode( $labelTemplate ) . ', ' )
			. '"propertyDefinitions": ' . self::PROPERTIES . '}';

		$propertyTypes = TestSources::newPropertyTypeRegistry();
		$schema = ( new SchemaPersistenceDeserializer( $propertyTypes ) )->deserialize( new SchemaName( 'Artwork' ), $json );

		return ( new LabelTemplateValidator( $propertyTypes ) )->errorsFor( $schema );
	}

	public function testTemplateNamingAMonolingualTextPropertyIsValid(): void {
		$this->assertSame( [], $this->errorsFor( '{Caption}' ) );
	}

	public function testSchemaWithoutTemplateIsValid(): void {
		$this->assertSame( [], $this->errorsFor( null ) );
	}

	public function testTemplateNamingAPropertyTheSchemaLacksIsInvalid(): void {
		$this->assertErrorNames( 'Material', $this->errorsFor( '{Title}, {Material}' ) );
	}

	/**
	 * @param string[] $errors
	 */
	private function assertErrorNames( string $propertyName, array $errors ): void {
		$this->assertCount( 1, $errors );
		$this->assertStringContainsString( $propertyName, $errors[0] );
	}

	public function testTemplateNamingARelationPropertyIsInvalidAndSaysItIsARelation(): void {
		$errors = $this->errorsFor( '{Title} by {Creator}' );

		$this->assertErrorNames( 'Creator', $errors );
		$this->assertStringContainsString( 'relation', $errors[0] );
	}

	public function testTemplateNamingABooleanPropertyIsInvalid(): void {
		$this->assertErrorNames( 'Framed', $this->errorsFor( '{Title} {Framed}' ) );
	}

	public function testTemplateNamingAPropertyOfAnUnregisteredTypeIsValid(): void {
		$this->assertSame( [], $this->errorsFor( '{Title} {Swatch}' ) );
	}

	public function testEachUnusablePlaceholderIsReported(): void {
		$this->assertCount( 2, $this->errorsFor( '{Material} by {Creator}' ) );
	}

	public function testTemplateNamingNoPropertyIsInvalid(): void {
		$this->assertCount( 1, $this->errorsFor( 'Artwork' ) );
	}

}
