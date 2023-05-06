<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\StringProperty;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinition;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions;
use ProfessionalWiki\NeoWiki\Domain\Schema\ValueFormat;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\PropertyDefinitions
 */
class PropertyDefinitionsTest extends TestCase {

	public function testFilter(): void {
		$properties = [
			'p1' => new StringProperty( ValueFormat::Text, 'foo' ),
			'p2' => new StringProperty( ValueFormat::Text, 'bar' ),
			'p3' => new StringProperty( ValueFormat::Text, 'foo' ),
			'p4' => new StringProperty( ValueFormat::Text, 'bar' ),
		];

		$propertyDefinitions = new PropertyDefinitions( $properties );

		$filteredProperties = $propertyDefinitions->filter( function( PropertyDefinition $property ) {
			return $property->getDescription() === 'foo';
		} );

		$this->assertTrue( $filteredProperties->hasProperty( 'p1' ) );
		$this->assertFalse( $filteredProperties->hasProperty( 'p2' ) );
		$this->assertTrue( $filteredProperties->hasProperty( 'p3' ) );
		$this->assertFalse( $filteredProperties->hasProperty( 'p4' ) );
	}

}
