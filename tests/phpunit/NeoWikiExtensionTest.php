<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 */
class NeoWikiExtensionTest extends TestCase {

	public function testGetInstanceIsSingleton(): void {
		$this->assertSame( NeoWikiExtension::getInstance(), NeoWikiExtension::getInstance() );
	}

	public function testPropertyTypeLookupReturnsNullForUnregisteredType(): void {
		$this->assertNull(
			NeoWikiExtension::getInstance()->getPropertyTypeLookup()->getType( 'does-not-exist' )
		);
	}

}
