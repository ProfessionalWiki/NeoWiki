<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectOption;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\SelectProperty;
use ProfessionalWiki\NeoWiki\Domain\Value\StringValue;
use ProfessionalWiki\NeoWiki\Tests\Data\TestProperty;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\SelectType
 */
class SelectTypeTest extends TestCase {

	public function testEveryChosenOptionContributesItsLabel(): void {
		$this->assertSame(
			[ 'In review', 'Published' ],
			( new SelectType() )->searchText( new StringValue( 'o2', 'o3' ), $this->statusProperty() )
		);
	}

	public function testIdTheSchemaNoLongerOffersIsSkipped(): void {
		$this->assertSame(
			[ 'Draft' ],
			( new SelectType() )->searchText( new StringValue( 'o1', 'o9' ), $this->statusProperty() )
		);
	}

	public function testValueWithoutADefinitionHasNoSearchText(): void {
		$this->assertSame( [], ( new SelectType() )->searchText( new StringValue( 'o2' ), null ) );
	}

	private function statusProperty(): SelectProperty {
		return TestProperty::buildSelect(
			new SelectOption( 'o1', 'Draft' ),
			new SelectOption( 'o2', 'In review' ),
			new SelectOption( 'o3', 'Published' ),
			new SelectOption( 'o4', 'Archived' )
		);
	}

}
