<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\MediaWiki;

use OutOfBoundsException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension;

/**
 * @covers \ProfessionalWiki\NeoWiki\MediaWiki\NeoWikiExtension
 */
class NeoWikiExtensionTest extends TestCase {

	public function testGetInstanceIsSingleton(): void {
		$this->assertSame( NeoWikiExtension::getInstance(), NeoWikiExtension::getInstance() );
	}

	public function testGetServiceFromPhpLibrary(): void {
		$this->expectException( OutOfBoundsException::class );
		NeoWikiExtension::getInstance()->getFormatRegistry()->getFormatOrThrow( 'does-not-exist' );
	}

}
