<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Tests\Domain\Schema\Property;

use PHPUnit\Framework\TestCase;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\DatePrecision;
use ProfessionalWiki\NeoWiki\Domain\Schema\Property\PartialDate;

/**
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\PartialDate
 * @covers \ProfessionalWiki\NeoWiki\Domain\Schema\Property\DatePrecision
 */
class PartialDateTest extends TestCase {

	public function testYearSpansTheWholeYear(): void {
		$date = PartialDate::tryParse( '1984' );

		$this->assertSame( DatePrecision::Year, $date->precision );
		$this->assertSame( '1984-01-01', $date->earliest->format( 'Y-m-d' ) );
		$this->assertSame( '1984-12-31', $date->latest->format( 'Y-m-d' ) );
	}

	public function testYearAndMonthSpansTheWholeMonth(): void {
		$date = PartialDate::tryParse( '1984-06' );

		$this->assertSame( DatePrecision::Month, $date->precision );
		$this->assertSame( '1984-06-01', $date->earliest->format( 'Y-m-d' ) );
		$this->assertSame( '1984-06-30', $date->latest->format( 'Y-m-d' ) );
	}

	public function testFebruaryOfALeapYearEndsOnThe29th(): void {
		$this->assertSame( '1984-02-29', PartialDate::tryParse( '1984-02' )->latest->format( 'Y-m-d' ) );
	}

	public function testFebruaryOfACommonYearEndsOnThe28th(): void {
		$this->assertSame( '1985-02-28', PartialDate::tryParse( '1985-02' )->latest->format( 'Y-m-d' ) );
	}

	public function testFullDateSpansOneDay(): void {
		$date = PartialDate::tryParse( '1984-06-15' );

		$this->assertSame( DatePrecision::Day, $date->precision );
		$this->assertSame( '1984-06-15', $date->earliest->format( 'Y-m-d' ) );
		$this->assertSame( '1984-06-15', $date->latest->format( 'Y-m-d' ) );
	}

	public function testBoundsAreAtUtcMidnight(): void {
		$date = PartialDate::tryParse( '1984' );

		$this->assertSame( '1984-01-01T00:00:00+00:00', $date->earliest->format( DATE_ATOM ) );
		$this->assertSame( '1984-12-31T00:00:00+00:00', $date->latest->format( DATE_ATOM ) );
	}

	/**
	 * @dataProvider malformedDateProvider
	 */
	public function testMalformedValueDoesNotParse( string $malformed ): void {
		$this->assertNull( PartialDate::tryParse( $malformed ) );
	}

	public static function malformedDateProvider(): iterable {
		yield 'has time component' => [ '2025-06-15T12:00:00Z' ];
		yield 'has midnight time component' => [ '2025-06-15T00:00:00' ];
		yield 'invalid month' => [ '2025-13' ];
		yield 'invalid month in full date' => [ '2025-13-01' ];
		yield 'invalid day' => [ '2025-02-30' ];
		yield 'non leap year Feb 29' => [ '2025-02-29' ];
		yield 'unpadded month' => [ '2025-6' ];
		yield 'two digit year' => [ '84' ];
		yield 'trailing dash' => [ '2025-' ];
		yield 'surrounding whitespace' => [ ' 2025 ' ];
		yield 'trailing newline' => [ "2025\n" ];
		yield 'year zero' => [ '0000' ];
		yield 'negative year' => [ '-0044' ];
		yield 'garbage' => [ 'not-a-date' ];
		yield 'empty string' => [ '' ];
	}

	public function testDayIsAtLeastMonth(): void {
		$this->assertTrue( DatePrecision::Day->isAtLeast( DatePrecision::Month ) );
	}

	public function testMonthIsAtLeastMonth(): void {
		$this->assertTrue( DatePrecision::Month->isAtLeast( DatePrecision::Month ) );
	}

	public function testYearIsNotAtLeastMonth(): void {
		$this->assertFalse( DatePrecision::Year->isAtLeast( DatePrecision::Month ) );
	}

	public function testMonthIsNotAtLeastDay(): void {
		$this->assertFalse( DatePrecision::Month->isAtLeast( DatePrecision::Day ) );
	}

}
