<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\PropertyType\Types;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\EdtfHumanizer;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\PropertyType\Types\EdtfHumanizer
 */
class EdtfHumanizerTest extends TestCase {

	private EdtfHumanizer $humanizer;

	protected function setUp(): void {
		$this->humanizer = new EdtfHumanizer();
	}

	public function testInvalidStringReturnsNull(): void {
		$this->assertNull( $this->humanizer->humanize( 'not-a-date', 'en' ) );
	}

	public function testUncertainDateReturnsHumanization(): void {
		$humanized = $this->humanizer->humanize( '1985?', 'en' );

		$this->assertNotNull( $humanized );
		$this->assertNotSame( '1985?', $humanized );
	}

	public function testSetReturnsHumanization(): void {
		$humanized = $this->humanizer->humanize( '{2010,2015..2020}', 'en' );

		$this->assertNotNull( $humanized );
		$this->assertStringContainsString( '2010', $humanized );
	}

}
