<?php

declare( strict_types = 1 );

namespace ProfessionalWiki\NeoWiki\Domain\Schema\Property;

use DateTimeImmutable;

/**
 * An EDTF level 0 calendar date of year, month or day precision (`YYYY`, `YYYY-MM`, `YYYY-MM-DD`),
 * read as the range of days it can stand for. Both ends are at UTC midnight. The other EDTF
 * forms are not accepted yet; when they are, this stays the fast path for these three.
 */
readonly class PartialDate {

	private const string YEAR = '([0-9]{4})';
	private const string MONTH = '(0[1-9]|1[0-2])';
	private const string DAY = '(0[1-9]|[12][0-9]|3[01])';

	/**
	 * The three forms. A subsequent calendar check rejects inputs like `2025-02-30` that the
	 * pattern alone cannot detect, and year zero. Carries no delimiters and stays within
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

		return new self(
			self::precisionOf( $month, $day ),
			$earliest,
			self::latestDayOf( $earliest, $month, $day ),
		);
	}

	private static function precisionOf( ?int $month, ?int $day ): DatePrecision {
		if ( $day !== null ) {
			return DatePrecision::Day;
		}

		return $month === null ? DatePrecision::Year : DatePrecision::Month;
	}

	private static function latestDayOf( DateTimeImmutable $earliest, ?int $month, ?int $day ): DateTimeImmutable {
		if ( $day !== null ) {
			return $earliest;
		}

		return $month === null
			? $earliest->modify( 'last day of december this year' )
			: $earliest->modify( 'last day of this month' );
	}

}
