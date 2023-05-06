<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\Schema;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaId;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Schema
 */
class SchemaTest extends TestCase {

	public function testPropertyExists(): void {
		$schema = $this->newSchemaWithProperties( [
			'name' => new StringProperty( format: ValueFormat::Text, description: 'The name of the company' ),
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
			'name' => new StringProperty( format: ValueFormat::Text, description: 'The name of the company' ),
			'ceo' => new RelationProperty( description: 'The company CEO', targetSchema: new SchemaId( 'Person' ) ),
		] );

		$this->assertFalse( $schema->isRelationProperty( 'name' ) );
		$this->assertTrue( $schema->isRelationProperty( 'ceo' ) );
	}

	public function testGetRelationProperties(): void {
		$schema = $this->newSchemaWithProperties( [
			'name' => new StringProperty( format: ValueFormat::Text, description: 'The name of the company' ),
			'ceo' => new RelationProperty( description: 'The company CEO', targetSchema: new SchemaId( 'Person' ) ),
			'foo' => new StringProperty( format: ValueFormat::Text, description: 'foo' ),
			'bar' => new RelationProperty( description: '', targetSchema: new SchemaId( 'Tomato' ) ),
		] );

		$this->assertEquals(
			new PropertyDefinitions( [
				'ceo' => new RelationProperty( description: 'The company CEO', targetSchema: new SchemaId( 'Person' ) ),
				'bar' => new RelationProperty( description: '', targetSchema: new SchemaId( 'Tomato' ) ),
			] ),
			$schema->getRelationProperties()
		);
	}

}
