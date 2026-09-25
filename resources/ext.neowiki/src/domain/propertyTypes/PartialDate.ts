/**
 * An ISO 8601 calendar date of year, month or day precision: `YYYY`, `YYYY-MM` or `YYYY-MM-DD`.
 */
export type PartialDate =
	{ precision: 'year'; year: number } |
	{ precision: 'month'; year: number; month: number } |
	{ precision: 'day'; year: number; month: number; day: number };

/**
 * A subsequent calendar check rejects inputs like `2025-02-30` that the regex alone cannot
 * detect, and year zero, which the backend does not accept either.
 */
// eslint-disable-next-line security/detect-unsafe-regex
const ISO_DATE_REGEX = /^(\d{4})(?:-(0[1-9]|1[0-2])(?:-(0[1-9]|[12]\d|3[01]))?)?$/;

export function parsePartialDate( value: string ): PartialDate | null {
	const match = ISO_DATE_REGEX.exec( value );

	if ( match === null ) {
		return null;
	}

	const year = Number( match[ 1 ] );

	if ( year === 0 ) {
		return null;
	}

	if ( match[ 2 ] === undefined ) {
		return { precision: 'year', year };
	}

	const month = Number( match[ 2 ] );

	if ( match[ 3 ] === undefined ) {
		return { precision: 'month', year, month };
	}

	const day = Number( match[ 3 ] );

	return day <= daysInMonth( year, month ) ? { precision: 'day', year, month, day } : null;
}

function daysInMonth( year: number, month: number ): number {
	const lastOfMonth = new Date( 0 );
	lastOfMonth.setUTCFullYear( year, month, 0 );

	return lastOfMonth.getUTCDate();
}
