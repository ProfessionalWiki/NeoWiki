<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Relation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationList;
use ProfessionalWiki\NeoWiki\Domain\Subject\SubjectId;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Relation\RelationList
 */
class RelationListTest extends TestCase {

	public function testGetTargetIdsOnEmptyList(): void {
		$this->assertSame(
			[],
			( new RelationList( [] ) )->getTargetIds()
		);
	}

	public function testGetTargetIds(): void {
		$this->assertEquals(
			[
				new SubjectId( '130A09E1-54FF-4A20-8A14-4E62D700AAAA' ),
				new SubjectId( '130A09E1-54FF-4A20-8A14-4E62D700AAAB' )
			],
			( new RelationList( [
				TestRelation::build( targetId: '130A09E1-54FF-4A20-8A14-4E62D700AAAA' ),
				TestRelation::build( targetId: '130A09E1-54FF-4A20-8A14-4E62D700AAAB' ),
			] ) )->getTargetIds()
		);
	}

}
