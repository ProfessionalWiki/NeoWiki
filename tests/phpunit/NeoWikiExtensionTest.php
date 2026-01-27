<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWikiExtension
 */
class NeoWikiExtensionTest extends TestCase {

	public function testGetInstanceIsSingleton(): void {
		$this->assertSame( NeoWikiExtension::getInstance(), NeoWikiExtension::getInstance() );
	}

	public function testGetServiceFromPhpLibrary(): void {
		$this->expectException( OutOfBoundsException::class );
		NeoWikiExtension::getInstance()->getPropertyTypeRegistry()->getTypeOrThrow( 'does-not-exist' );
	}

}
