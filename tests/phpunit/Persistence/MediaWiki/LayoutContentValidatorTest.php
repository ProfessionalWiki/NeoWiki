<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Persistence\MediaWiki;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutContentValidator;

/**
 * @covers \ProfessionalWiki\NeoWiki\Persistence\MediaWiki\LayoutContentValidator
 */
class LayoutContentValidatorTest extends TestCase {

	public function testMinimalValidLayout(): void {
		$this->assertTrue(
			LayoutContentValidator::newInstance()->validate(
				'{ "schema": "Company", "type": "infobox" }'
			)
		);
	}

	public function testFullValidLayout(): void {
		$this->assertTrue(
			LayoutContentValidator::newInstance()->validate( json_encode( [
				'schema' => 'Company',
				'type' => 'infobox',
				'description' => 'Key financial data',
				'displayRules' => [
					[ 'property' => 'Revenue', 'displayAttributes' => [ 'precision' => 0 ] ],
					[ 'property' => 'Net Income' ],
				],
				'settings' => [ 'borderColor' => '#336699' ],
			] ) )
		);
	}

	public function testEmptyDisplayRulesIsValid(): void {
		$this->assertTrue(
			LayoutContentValidator::newInstance()->validate(
				'{ "schema": "Company", "type": "infobox", "displayRules": [] }'
			)
		);
	}

	public function testEmptyJsonFails(): void {
		$this->assertFalse(
			LayoutContentValidator::newInstance()->validate( '{}' )
		);
	}

	public function testMissingSchemaFails(): void {
		$validator = LayoutContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate( '{ "type": "infobox" }' )
		);

		$this->assertSame(
			[ '/' => 'The required properties (schema) are missing' ],
			$validator->getErrors()
		);
	}

	public function testMissingTypeFails(): void {
		$validator = LayoutContentValidator::newInstance();

		$this->assertFalse(
			$validator->validate( '{ "schema": "Company" }' )
		);

		$this->assertSame(
			[ '/' => 'The required properties (type) are missing' ],
			$validator->getErrors()
		);
	}

	public function testEmptySchemaNameFails(): void {
		$this->assertFalse(
			LayoutContentValidator::newInstance()->validate(
				'{ "schema": "", "type": "infobox" }'
			)
		);
	}

	public function testEmptyTypeNameFails(): void {
		$this->assertFalse(
			LayoutContentValidator::newInstance()->validate(
				'{ "schema": "Company", "type": "" }'
			)
		);
	}

	public function testDisplayRuleWithoutPropertyFails(): void {
		$this->assertFalse(
			LayoutContentValidator::newInstance()->validate( json_encode( [
				'schema' => 'Company',
				'type' => 'infobox',
				'displayRules' => [
					[ 'displayAttributes' => [ 'precision' => 0 ] ],
				],
			] ) )
		);
	}

	public function testStructurallyInvalidJsonFails(): void {
		$this->assertFalse(
			LayoutContentValidator::newInstance()->validate( '}{' )
		);
	}

}
