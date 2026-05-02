<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\DateTimeType
 */
class DateTimeTypeTest extends TestCase {

	public function testHasNoDisplayAttributes(): void {
		$this->assertSame( [], ( new DateTimeType() )->getDisplayAttributeNames() );
	}

}
