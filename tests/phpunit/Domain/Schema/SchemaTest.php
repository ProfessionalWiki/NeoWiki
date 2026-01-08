<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Schema
 */
class SchemaTest extends TestCase {

	public function testPropertyExists(): void {
		$schema = $this->newSchemaWithProperties( [
			'name' => TestProperty::buildText(),
		] );

		$this->assertTrue( $schema->hasProperty( 'name' ) );
		$this->assertFalse( $schema->hasProperty( 'foo' ) );
	}

	private function newSchemaWithProperties( array $propertyDefinitions ): Schema {
		return new Schema(
			name: new SchemaName( 'Company' ),
			description: 'A company',
			properties: new PropertyDefinitions( $propertyDefinitions )
		);
	}

	public function testIsRelationProperty(): void {
		$schema = $this->newSchemaWithProperties( [
			'name' => TestProperty::buildText(),
			'ceo' => TestProperty::buildRelation(),
		] );

		$this->assertFalse( $schema->isRelationProperty( 'name' ) );
		$this->assertTrue( $schema->isRelationProperty( 'ceo' ) );
	}

	public function testGetRelationProperties(): void {
		$ceoProperty = TestProperty::buildRelation( description: 'The company CEO', targetSchema: new SchemaName( 'Person' ) );
		$barProperty = TestProperty::buildRelation( targetSchema: new SchemaName( 'Tomato' ) );

		$schema = $this->newSchemaWithProperties( [
			'name' => TestProperty::buildText(),
			'ceo' => $ceoProperty,
			'foo' => TestProperty::buildText(),
			'bar' => $barProperty,
		] );

		$this->assertEquals(
			new PropertyDefinitions( [
				'ceo' => $ceoProperty,
				'bar' => $barProperty,
			] ),
			$schema->getRelationProperties()
		);
	}

}
