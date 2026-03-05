<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\View;

use InvalidArgumentException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\View\ViewName;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\View\ViewName
 */
class ViewNameTest extends TestCase {

	public function testGetText(): void {
		$viewName = new ViewName( 'FinancialOverview' );

		$this->assertSame( 'FinancialOverview', $viewName->getText() );
	}

	public function testEmptyNameIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );

		new ViewName( '' );
	}

	public function testWhitespaceOnlyNameIsInvalid(): void {
		$this->expectException( InvalidArgumentException::class );

		new ViewName( '   ' );
	}

}
