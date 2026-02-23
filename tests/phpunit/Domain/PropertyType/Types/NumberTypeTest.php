<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\NumberType;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\NumberType
 */
class NumberTypeTest extends TestCase {

	public function testDisplayAttributeNamesContainsPrecision(): void {
		$this->assertSame( [ 'precision' ], ( new NumberType() )->getDisplayAttributeNames() );
	}

}
