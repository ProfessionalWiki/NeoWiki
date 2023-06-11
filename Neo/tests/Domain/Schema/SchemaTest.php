<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Schema
 */
class SchemaTest extends TestCase {

	public function testPropertyExists(): void {
		$schema = $this->newSchemaWithProperties( [
			'name' => TestProperty::buildString(),
		] );

		$this->assertTrue( $schema->hasProperty( 'name' ) );
		$this->assertFalse( $schema->hasProperty( 'foo' ) );
	}

	private function newSchemaWithProperties( array $propertyDefinitions ): Schema {
		return new Schema(
			id: new SchemaId( 'Company' ),
			description: 'A company',
			properties: new PropertyDefinitions( $propertyDefinitions )
		);
	}

	public function testIsRelationProperty(): void {
		$schema = $this->newSchemaWithProperties( [
			'name' => TestProperty::buildString(),
			'ceo' => TestProperty::buildRelation(),
		] );

		$this->assertFalse( $schema->isRelationProperty( 'name' ) );
		$this->assertTrue( $schema->isRelationProperty( 'ceo' ) );
	}

	public function testGetRelationProperties(): void {
		$ceoProperty = TestProperty::buildRelation( description: 'The company CEO', targetSchema: new SchemaId( 'Person' ) );
		$barProperty = TestProperty::buildRelation( targetSchema: new SchemaId( 'Tomato' ) );

		$schema = $this->newSchemaWithProperties( [
			'name' => TestProperty::buildString(),
			'ceo' => $ceoProperty,
			'foo' => TestProperty::buildString(),
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
