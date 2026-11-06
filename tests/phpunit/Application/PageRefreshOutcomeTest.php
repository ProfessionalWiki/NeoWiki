<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Application;

use LogicException;
use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome;

/**
 * @covers \ProfessionalWiki\NeoWiki\Application\PageRefreshOutcome
 */
class PageRefreshOutcomeTest extends TestCase {

	/**
	 * The outcomes of a page that was written rather than skipped.
	 */
	private const array WRITE_OUTCOMES = [ PageRefreshOutcome::Refreshed, PageRefreshOutcome::Unpublished ];

	/**
	 * @dataProvider skipOutcomeProvider
	 */
	public function testEverySkipOutcomeExplainsItself( PageRefreshOutcome $outcome ): void {
		$this->assertNotSame( '', $outcome->skipReason() );
	}

	/**
	 * @return iterable<array{PageRefreshOutcome}>
	 */
	public static function skipOutcomeProvider(): iterable {
		foreach ( PageRefreshOutcome::cases() as $outcome ) {
			if ( !in_array( $outcome, self::WRITE_OUTCOMES, true ) ) {
				yield $outcome->value => [ $outcome ];
			}
		}
	}

	/**
	 * @dataProvider writeOutcomeProvider
	 */
	public function testAnOutcomeThatWroteHasNoSkipReason( PageRefreshOutcome $outcome ): void {
		$this->expectException( LogicException::class );

		$outcome->skipReason();
	}

	/**
	 * @return iterable<array{PageRefreshOutcome}>
	 */
	public static function writeOutcomeProvider(): iterable {
		foreach ( self::WRITE_OUTCOMES as $outcome ) {
			yield $outcome->value => [ $outcome ];
		}
	}

}
