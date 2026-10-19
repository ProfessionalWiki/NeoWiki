<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Presentation;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Presentation\PageIdSummary;

/**
 * @covers \ProfessionalWiki\NeoWiki\Presentation\PageIdSummary
 */
class PageIdSummaryTest extends TestCase {

	public function testNamesEveryPageOfAHandful(): void {
		$this->assertSame( '3, 7, 11, 12, 20', PageIdSummary::of( [ 3, 7, 11, 12, 20 ] ) );
	}

	public function testNamesTheFirstHandfulAndCountsTheRest(): void {
		$this->assertSame( '3, 7, 11, 12, 20 and 2 more', PageIdSummary::of( [ 3, 7, 11, 12, 20, 21, 30 ] ) );
	}

}
