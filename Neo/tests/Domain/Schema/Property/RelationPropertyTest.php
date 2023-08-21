<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Property\Schema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\SchemaName;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\RelationProperty
 * @covers \ProfessionalWiki\NeoWiki\Domain\Relation\RelationType
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition
 */
class RelationPropertyTest extends TestCase {

	public function testGetters(): void {
		$property = new RelationProperty(
			description: 'foo',
			required: true,
			default: null,
			relationType: new RelationType( 'Type' ),
			targetSchema: new SchemaName( 'Schema' ),
			multiple: true
		);

		$this->assertSame( 'foo', $property->getDescription() );
		$this->assertTrue( $property->isRequired() );
		$this->assertNull( $property->getDefault() );
		$this->assertSame( 'Type', $property->getRelationType()->getText() );
		$this->assertSame( 'Schema', $property->getTargetSchema()->getText() );
		$this->assertTrue( $property->isMultiple() );
	}

}
