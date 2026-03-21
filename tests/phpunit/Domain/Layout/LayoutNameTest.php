<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Layout;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Layout\LayoutName
 */
class LayoutNameTest extends TestCase {

	public function testGetText(): void {
		$layoutName = new LayoutName( 'FinancialOverview' );

		$this->assertSame( 'FinancialOverview', $layoutName->getText() );
	}

	public function testEmptyNameIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );

		new LayoutName( '' );
	}

	public function testWhitespaceOnlyNameIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );

		new LayoutName( '   ' );
	}

}
