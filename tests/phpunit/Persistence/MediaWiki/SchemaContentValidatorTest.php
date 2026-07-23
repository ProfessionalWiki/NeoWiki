<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaContentValidator;
use ProfessionalWiki\NeoWiki\Tests\Data\TestData;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaContentValidator
 */
class SchemaContentValidatorTest extends TestCase {

	/**
	 * @dataProvider exampleSchemaProvider
	 */
	public function testExampleSchemaIsValid( string $data ): void {
		$validator = SchemaContentValidator::newInstance();

		$valid = $validator->validate( $data );

		if ( !$valid ) {
			$this->assertSame( [], $validator->getErrors() );
		}

		$this->assertTrue( $valid );
	}

	public function exampleSchemaProvider(): iterable {
		yield [ TestData::getFileContents( 'Schema/Employee.json' ) ];
		yield [ TestData::getFileContents( 'Schema/Company.json' ) ];
		yield [ TestData::getFileContents( 'Schema/Product.json' ) ];
		yield [ TestData::getFileContents( 'Schema/Everything.json' ) ];
	}

	public function testEmptyJsonFailsValidation(): void {
		$this->assertFalse(
			SchemaContentValidator::newInstance()->validate( '{}' )
		);
	}

	public function testStructurallyInvalidJsonFailsValidation(): void {
		$this->assertFalse(
			SchemaContentValidator::newInstance()->validate( '}{' )
		);
	}

	public function testMissingPropertyDefinitionsFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				'{ "notPropertyDefinitions": {}, "relations": {} }'
			)
		);

		$this->assertSame(
			[ '/' => 'The required properties (propertyDefinitions) are missing' ],
			$validator->getErrors()
		);
	}

	public function testExtensionDefinedTypePassesValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$valid = $validator->validate(
			<<<JSON
{
	"propertyDefinitions": {
		"favouriteColor": {
			"type": "color"
		}
	}
}
JSON
		);

		if ( !$valid ) {
			$this->assertSame( [], $validator->getErrors() );
		}

		$this->assertTrue( $valid );
	}

	public function testEmptyTypeFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				<<<JSON
{
	"propertyDefinitions": {
		"someProperty": {
			"type": ""
		}
	}
}
JSON
			)
		);
	}

	public function testMissingTypeFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				<<<JSON
{
	"propertyDefinitions": {
		"someProperty": {
			"description": "no type"
		}
	}
}
JSON
			)
		);
	}

	public function testValidRelationPropertyPassesValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$valid = $validator->validate(
			$this->schemaWithProperty( '{ "type": "relation", "relation": "Likes", "targetSchema": "Person" }' )
		);

		if ( !$valid ) {
			$this->assertSame( [], $validator->getErrors() );
		}

		$this->assertTrue( $valid );
	}

	public function testEmptyRelationTypeFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "relation", "relation": "", "targetSchema": "Person" }' )
			)
		);

		$this->assertContains(
			'The relation type must not be empty or have surrounding whitespace.',
			$validator->getErrors()
		);
	}

	public function testWhitespaceOnlyRelationTypeFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "relation", "relation": "   ", "targetSchema": "Person" }' )
			)
		);

		$this->assertContains(
			'The relation type must not be empty or have surrounding whitespace.',
			$validator->getErrors()
		);
	}

	public function testRelationTypeWithSurroundingWhitespaceFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "relation", "relation": "Likes ", "targetSchema": "Person" }' )
			)
		);

		$this->assertContains(
			'The relation type must not be empty or have surrounding whitespace.',
			$validator->getErrors()
		);
	}

	public function testEmptyTargetSchemaFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "relation", "relation": "Likes", "targetSchema": "" }' )
			)
		);

		$this->assertContains(
			'The target schema must not be empty or have surrounding whitespace.',
			$validator->getErrors()
		);
	}

	public function testTargetSchemaWithSurroundingWhitespaceFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "relation", "relation": "Likes", "targetSchema": " Person" }' )
			)
		);

		$this->assertContains(
			'The target schema must not be empty or have surrounding whitespace.',
			$validator->getErrors()
		);
	}

	public function testRequiredWithSeverityObjectFormPassesValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$valid = $validator->validate(
			$this->schemaWithProperty( '{ "type": "text", "required": { "severity": "error" } }' )
		);

		if ( !$valid ) {
			$this->assertSame( [], $validator->getErrors() );
		}

		$this->assertTrue( $valid );
	}

	public function testScalarConstraintWithValidSeverityObjectFormPassesValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$valid = $validator->validate(
			$this->schemaWithProperty( '{ "type": "number", "maximum": { "value": 100, "severity": "error" } }' )
		);

		if ( !$valid ) {
			$this->assertSame( [], $validator->getErrors() );
		}

		$this->assertTrue( $valid );
	}

	public function testScalarConstraintWithInvalidSeverityStringFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "number", "maximum": { "value": 100, "severity": "eror" } }' )
			)
		);
	}

	public function testScalarConstraintObjectFormMissingValueFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "number", "maximum": { "severity": "error" } }' )
			)
		);
	}

	/**
	 * The boolean object form implies true and carries no value key. Permitting a stray
	 * one would let "value": false round-trip back as true, silently flipping the Constraint.
	 */
	public function testBooleanConstraintObjectFormWithValueKeyFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "text", "required": { "value": false, "severity": "error" } }' )
			)
		);
	}

	/**
	 * Wraps the property under test between two valid siblings so a regression that only
	 * inspects the first or last property definition is caught.
	 */
	private function schemaWithProperty( string $propertyJson ): string {
		return <<<JSON
{
	"propertyDefinitions": {
		"before": { "type": "text" },
		"offending": $propertyJson,
		"after": { "type": "text" }
	}
}
JSON;
	}

}
