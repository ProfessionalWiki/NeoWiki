<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaContentValidator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\SchemaContentValidator
 */
class SchemaContentValidatorTest extends TestCase {

	public function testEmployeeSubjectIsValid(): void {
		$validator = SchemaContentValidator::newInstance();
		$data = $this->getEmployeeSchemaData();

		$valid = $validator->validate( $data );

		if ( !$valid ) {
			$this->assertSame( [], $validator->getErrors() );
		}

		$this->assertTrue( $valid );
	}

	private function getEmployeeSchemaData(): string {
		return file_get_contents( __DIR__ . '/../../Data/employeeSchema.json' );
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

	public function testMissingRelationsFailsValidation(): void {
		$validator = SchemaContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate(
				'{ "title": "Foo Bar", "propertyDefinitions": {}, "notRelations": {} }'
			)
		);

		$this->assertSame(
			[ '/' => 'The required properties (relations) are missing' ],
			$validator->getErrors()
		);
	}

}
