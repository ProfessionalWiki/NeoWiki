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

	public function testInvalidFormatFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				<<<JSON
{
	"propertyDefinitions": {
		"someRelation": {
			"type": "not a real format"
		}
	}
}
JSON
			)
		);

		$this->assertSame(
			[ '/propertyDefinitions/someRelation/type' => 'The data should match one item from enum' ],
			$validator->getErrors()
		);
	}

	public function testMissingRelationPropertyDefinitionsFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				<<<JSON
{
	"propertyDefinitions": {
		"someRelation": {
			"type": "relation"
		}
	}
}
JSON
			)
		);

		$this->assertSame(
			[
				'/propertyDefinitions/someRelation/type' => 'The data must match the const value',
				'/propertyDefinitions/someRelation' => 'The required properties (relation, targetSchema) are missing'
			],
			$validator->getErrors()
		);
	}

}
