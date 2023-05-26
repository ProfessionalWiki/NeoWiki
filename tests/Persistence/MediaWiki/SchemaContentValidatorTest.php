<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaContentValidator;

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
		yield [ file_get_contents( __DIR__ . '/../../../DemoData/Schema/Employee.json' ) ];
		yield [ file_get_contents( __DIR__ . '/../../../DemoData/Schema/Company.json' ) ];
		yield [ file_get_contents( __DIR__ . '/../../../DemoData/Schema/Product.json' ) ];
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

	public function testMissingTitleFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				'{ "notTitle": "Foo Bar", "propertyDefinitions": {}, "relations": {} }'
			)
		);

		$this->assertSame(
			[ '/' => 'The required properties (title) are missing' ],
			$validator->getErrors()
		);
	}

	public function testMissingPropertyDefinitionsFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				'{ "title": "Foo Bar", "notPropertyDefinitions": {}, "relations": {} }'
			)
		);

		$this->assertSame(
			[ '/' => 'The required properties (propertyDefinitions) are missing' ],
			$validator->getErrors()
		);
	}

}
