<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Relation;

use PHPUnit\Framework\Attributes\CoversClass;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Relation\RelationType;
use ProfessionalWiki\NeoWiki\Domain\Relation\TypedRelationList;
use ProfessionalWiki\NeoWiki\Tests\Data\TestRelation;

#[CoversClass( TypedRelationList::class )]
class TypedRelationListTest extends TestCase {

	public function testGetIdsAsStringArray(): void {
		$this->assertSame(
			[
				'130A09E1-54FF-4A20-8A14-4E62D700AAAA',
				'130A09E1-54FF-4A20-8A14-4E62D700AAAB'
			],
			( new TypedRelationList( [
				TestRelation::build( id: '130A09E1-54FF-4A20-8A14-4E62D700AAAA' )->withType( new RelationType( 'FriendOf' ) ),
				TestRelation::build( id: '130A09E1-54FF-4A20-8A14-4E62D700AAAB' )->withType( new RelationType( 'FriendOf' ) ),
			] ) )->getIdsAsStringArray()
		);
	}

}
