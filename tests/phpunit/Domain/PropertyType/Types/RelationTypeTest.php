<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Value\RelationValue;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\PropertyType;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\RelationType
 */
class RelationTypeTest extends TestCase {

	public function testBuildNeo4jValueReturnsSkipIndicator(): void {
		$this->assertSame(
			PropertyType::NO_NEO4J_VALUE,
			$this->newType()->buildNeo4jValue( new RelationValue( TestRelation::build() ) )
		);
	}

	private function newType(): RelationType {
		return new RelationType();
	}

}
