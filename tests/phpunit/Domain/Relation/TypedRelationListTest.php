<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Relation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;
use ProfessionalWiki\NeoWiki\Domain\Schema\PropertyName;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList
 */
class TypedRelationListTest extends TestCase {

	public function testGetIdsAsStringArray(): void {
		$this->assertSame(
			[
				'r11111111111113',
				'r11111111111114'
			],
			( new TypedRelationList( [
				TestRelation::build( id: 'r11111111111113' )->withPropertyName( new PropertyName( 'Friends' ) ),
				TestRelation::build( id: 'r11111111111114' )->withPropertyName( new PropertyName( 'Friends' ) ),
			] ) )->getIdsAsStringArray()
		);
	}

}
