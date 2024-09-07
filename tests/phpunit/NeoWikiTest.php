<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\NeoWiki;

/**
 * @covers \ProfessionalWiki\NeoWiki\NeoWiki
 */
class NeoWikiTest extends TestCase {

	public function testGetInstanceIsSingleton(): void {
		$this->assertSame( NeoWiki::getInstance(), NeoWiki::getInstance() );
	}

}
