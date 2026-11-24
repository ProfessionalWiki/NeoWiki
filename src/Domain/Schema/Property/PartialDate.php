<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use DateTimeImmutable;

/**
 * A calendar date of year, month or day precision (`YYYY`, `YYYY-MM`, `YYYY-MM-DD`), read as the
 * range of days it can stand for. Both ends are at UTC midnight.
 */
readonly class PartialDate {

	private const string YEAR = '(?!0000)([0-9]{4})';
	private const string MONTH = '(0[1-9]|1[0-2])';
	private const string DAY = '(0[1-9]|[12][0-9]|3[01])';

	/**
	 * The three forms. A subsequent calendar check rejects inputs like `2025-02-30` that the
	 * pattern alone cannot detect. Carries no delimiters and stays within
	 * ECMA-262, so that the JSON Schema documents can use it as a `pattern`.
	 */
	public const string PATTERN = '^' . self::YEAR . '(?:-' . self::MONTH . '(?:-' . self::DAY . ')?)?$';

	private const string ISO_DATE_REGEX = '/' . self::PATTERN . '/D';

	/**
	 * The forms of at least the given precision, in the same ECMA-262 shape as PATTERN.
	 */
	public static function patternOfAtLeast( DatePrecision $precision ): string {
		return match ( $precision ) {
			DatePrecision::Year => self::PATTERN,
			DatePrecision::Month => '^' . self::YEAR . '-' . self::MONTH . '(?:-' . self::DAY . ')?$',
			DatePrecision::Day => '^' . self::YEAR . '-' . self::MONTH . '-' . self::DAY . '$',
		};
	}

	private function __construct(
		public DatePrecision $precision,
		public DateTimeImmutable $earliest,
		public DateTimeImmutable $latest,
	) {
	}

	public static function tryParse( string $value ): ?self {
		if ( preg_match( self::ISO_DATE_REGEX, $value, $matches ) !== 1 ) {
			return null;
		}

		$year = (int)$matches[1];
		$month = isset( $matches[2] ) ? (int)$matches[2] : null;
		$day = isset( $matches[3] ) ? (int)$matches[3] : null;

		if ( !checkdate( $month ?? 1, $day ?? 1, $year ) ) {
			return null;
		}

		$earliest = ( new DateTimeImmutable( '@0' ) )->setDate( $year, $month ?? 1, $day ?? 1 );
		$precision = self::precisionOf( $month, $day );

		return new self( $precision, $earliest, self::latestDayOf( $earliest, $precision ) );
	}

	private static function precisionOf( ?int $month, ?int $day ): DatePrecision {
		if ( $day !== null ) {
			return DatePrecision::Day;
		}

		return $month === null ? DatePrecision::Year : DatePrecision::Month;
	}

	private static function latestDayOf( DateTimeImmutable $earliest, DatePrecision $precision ): DateTimeImmutable {
		return match ( $precision ) {
			DatePrecision::Year => $earliest->modify( 'last day of december this year' ),
			DatePrecision::Month => $earliest->modify( 'last day of this month' ),
			DatePrecision::Day => $earliest,
		};
	}

}
