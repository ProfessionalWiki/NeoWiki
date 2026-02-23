<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\UrlType
 */
class UrlTypeTest extends TestCase {

	public function testDisplayAttributeNamesIsEmpty(): void {
		$this->assertSame( [], ( new UrlType() )->getDisplayAttributeNames() );
	}

}
