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
			'The target schema must be a Schema name, or an object with a source key and a name. Neither may be empty or have surrounding whitespace.',
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
			'The target schema must be a Schema name, or an object with a source key and a name. Neither may be empty or have surrounding whitespace.',
			$validator->getErrors()
		);
	}

	public function testColonBearingTargetSchemaPassesValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertTrue(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "relation", "relation": "Likes", "targetSchema": "ISO:9001" }' )
			)
		);
	}

	public function testSourcedTargetSchemaObjectPassesValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertTrue(
			$validator->validate(
				$this->schemaWithProperty(
					'{ "type": "relation", "relation": "Likes", "targetSchema": { "source": "otherwiki", "name": "Person" } }'
				)
			)
		);
	}

	public function testTargetSchemaObjectWithoutANameFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty(
					'{ "type": "relation", "relation": "Likes", "targetSchema": { "source": "otherwiki" } }'
				)
			)
		);
	}

	public function testTargetSchemaObjectWithAMalformedSourceKeyFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty(
					'{ "type": "relation", "relation": "Likes", "targetSchema": { "source": "2wiki", "name": "Person" } }'
				)
			)
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

	public function testOptionsWithSeverityObjectFormPassesValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$valid = $validator->validate(
			$this->schemaWithProperty(
				'{ "type": "select", "multiple": false, "options": { "value": [ { "id": "opt_a", "label": "A" } ], "severity": "error" } }'
			)
		);

		if ( !$valid ) {
			$this->assertSame( [], $validator->getErrors() );
		}

		$this->assertTrue( $valid );
	}

	/**
	 * `multiple` declares the value's shape rather than a Constraint, so the object form is
	 * rejected at authoring time: the normalizer is deliberately key-agnostic and would
	 * otherwise unwrap it into a severity nothing reads.
	 */
	public function testShapeKeyWithSeverityObjectFormFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "text", "multiple": { "severity": "error" } }' )
			)
		);
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
	 * The positive `options` case above cannot fail on its own: a key left declared but
	 * unconstrained still accepts the object form, and being declared also puts it outside the
	 * additionalProperties guard. These two pin that the $ref is actually wired up — without it
	 * a typo'd severity saves cleanly and then throws on every subsequent read of the Schema page.
	 */
	public function testOptionsObjectFormWithInvalidSeverityFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty(
					'{ "type": "select", "multiple": false, "options": { "value": [ { "id": "opt_a", "label": "A" } ], "severity": "eror" } }'
				)
			)
		);
	}

	public function testOptionsObjectFormMissingValueFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty( '{ "type": "select", "multiple": false, "options": { "severity": "error" } }' )
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
	 * A custom Property Type's own Constraint keys are not declared here, so only the
	 * additionalProperties guard checks their severity. Without it the typo saves cleanly and
	 * SeverityNormalizer::extract then throws on every read, which SchemaPersistenceDeserializer
	 * swallows — silently removing the property from the Schema with no error and no log.
	 */
	public function testCustomKeyObjectFormWithInvalidSeverityFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty(
					'{ "type": "color", "allowedColors": { "value": [ "#ff0000" ], "severity": "eror" } }'
				)
			)
		);
	}

	public function testCustomKeyObjectFormWithValidSeverityPassesValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$valid = $validator->validate(
			$this->schemaWithProperty(
				'{ "type": "color", "allowedColors": { "value": [ "#ff0000" ], "severity": "error" } }'
			)
		);

		if ( !$valid ) {
			$this->assertSame( [], $validator->getErrors() );
		}

		$this->assertTrue( $valid );
	}

	/**
	 * `default` is reserved: SeverityNormalizer never treats it as a Constraint, so a default
	 * value that happens to be an object carrying a `severity` key is data, not an annotation,
	 * and the guard on unlisted keys must not reject it.
	 */
	public function testReservedDefaultKeyCarryingArbitrarySeverityPassesValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$valid = $validator->validate(
			$this->schemaWithProperty( '{ "type": "color", "default": { "severity": "high" } }' )
		);

		if ( !$valid ) {
			$this->assertSame( [], $validator->getErrors() );
		}

		$this->assertTrue( $valid );
	}

	/**
	 * extract() reads only `value` and `severity`, so a stray sibling is silently discarded.
	 * booleanConstraint already closes its object; scalarConstraint must too, or a typo'd
	 * `sevrity` looks accepted while the Constraint keeps the default warning.
	 */
	public function testScalarConstraintObjectFormWithUnknownKeyFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				$this->schemaWithProperty(
					'{ "type": "number", "maximum": { "value": 100, "severity": "error", "sevrity": "warning" } }'
				)
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
