import { parsePartialDate } from '@/domain/propertyTypes/PartialDate';

/**
 * What a text someone typed into a date field says. A date carries the EDTF form it is stored
 * as; a slashed date that reads two ways (`06/07/1984`) carries the other reading too, so the
 * field can offer it.
 */
export type DateTextReading =
	{ kind: 'empty' } |
	{ kind: 'date'; iso: string; alternative?: string } |
	{ kind: 'unreadable'; problem: 'not-a-date' | 'invalid-month' | 'year-zero' } |
	{ kind: 'unreadable'; problem: 'invalid-day'; daysInMonth: number };

interface DateParts {
	year: number;
	month: number | null;
	day: number | null;
}

/**
 * Reads a date the way people write them: the ISO forms of any precision, year-first and
 * day-first numeric forms, and month names in the reader's language or English, as `Intl`
 * writes them in a date (`15 czerwca 1984`) or on their own (`czerwiec`). Digits of any
 * script are read, a day may carry its ordinal (`15th`, `1er`), and words such as `de` or a
 * year marker (`1984 г.`) are ignored once a month name is found. A slashed `a/b/year` reads
 * day first, since the language alone does not say which order the reader uses, and the
 * month-first reading is returned as the alternative when it is a date too. Two-digit years
 * are refused rather than given a century.
 */
export function readDateText( text: string, language: string ): DateTextReading {
	const normalized = normalize( text, language );

	if ( normalized === '' ) {
		return { kind: 'empty' };
	}

	const readings = partsOf( normalized, language );

	if ( readings === null ) {
		return { kind: 'unreadable', problem: 'not-a-date' };
	}

	const first = checked( readings[ 0 ] );

	if ( first.kind !== 'date' ) {
		return first;
	}

	const second = readings[ 1 ] === undefined ? null : checked( readings[ 1 ] );

	return second !== null && second.kind === 'date' && second.iso !== first.iso ?
		{ kind: 'date', iso: first.iso, alternative: second.iso } :
		{ kind: 'date', iso: first.iso };
}

/**
 * The text a field shows for a stored date: the way the wiki displays it, as long as reading
 * that gives the date back, and otherwise the stored form, which always does.
 */
export function dateTextOf( iso: string, language: string ): string {
	const display = formatDateForDisplay( iso, language );
	const readBack = readDateText( display, language );

	return readBack.kind === 'date' && readBack.iso === iso && readBack.alternative === undefined ? display : iso;
}

/**
 * Formats a `YYYY`, `YYYY-MM` or `YYYY-MM-DD` string as a human-readable date in the given
 * language, showing only the parts the value has and no time component. It stays in the
 * Gregorian calendar the value is written in: a year or month does not map onto the years or
 * months of another calendar. What it writes, `readDateText` reads back.
 *
 * The date is interpreted in UTC and rendered with `timeZone: 'UTC'` so the
 * displayed calendar day always matches the stored day regardless of the host
 * timezone. Falls back to the raw input when it cannot be parsed, so malformed
 * values surface verbatim in the UI rather than as `Invalid Date`.
 */
export function formatDateForDisplay( iso: string, language: string ): string {
	const date = parsePartialDate( iso );

	if ( date === null ) {
		return iso;
	}

	const utcDate = new Date( 0 );
	utcDate.setUTCFullYear(
		date.year,
		date.precision === 'year' ? 0 : date.month - 1,
		date.precision === 'day' ? date.day : 1,
	);

	return dateTimeFormat( language, {
		calendar: 'gregory',
		year: 'numeric',
		month: date.precision === 'year' ? undefined : 'short',
		day: date.precision === 'day' ? 'numeric' : undefined,
		timeZone: 'UTC',
	} ).format( utcDate );
}

/**
 * A wiki language code is not always a locale `Intl` knows; English stands in then.
 */
function dateTimeFormat( language: string, options: Intl.DateTimeFormatOptions ): Intl.DateTimeFormat {
	try {
		return new Intl.DateTimeFormat( language, options );
	} catch {
		return new Intl.DateTimeFormat( 'en', options );
	}
}

// Marks and joiners that come along when a date is pasted from a right-to-left page or a form.
const INVISIBLE = /[\u200B-\u200F\u2060\uFEFF]/g;

function normalize( text: string, language: string ): string {
	const plain = lowerCased( asciiDigits( text.replace( INVISIBLE, '' ) ), language ).replace( /\s+/g, ' ' ).trim();

	return withoutYearMarker( plain, language );
}

// In the reader's language, so that Turkish `HAZİRAN` becomes `haziran` rather than `hazi̇ran`.
function lowerCased( text: string, language: string ): string {
	try {
		return text.toLocaleLowerCase( language );
	} catch {
		return text.toLowerCase();
	}
}

// The first digit of every run of ten decimal digits Unicode has: ASCII, Arabic-Indic, Persian,
// N'Ko, the Indic scripts, Thai, Lao, Tibetan, Myanmar and so on to the fullwidth digits.
const DIGIT_ZEROS = [
	0x30, 0x660, 0x6F0, 0x7C0, 0x966, 0x9E6, 0xA66, 0xAE6, 0xB66, 0xBE6, 0xC66, 0xCE6, 0xD66, 0xDE6,
	0xE50, 0xED0, 0xF20, 0x1040, 0x1090, 0x17E0, 0x1810, 0x1946, 0x19D0, 0x1A80, 0x1A90, 0x1B50,
	0x1BB0, 0x1C40, 0x1C50, 0xA620, 0xA8D0, 0xA900, 0xA9D0, 0xA9F0, 0xAA50, 0xABF0, 0xFF10,
];

function asciiDigits( text: string ): string {
	return text.replace( /\p{Nd}/gu, ( digit ) => {
		const code = digit.codePointAt( 0 ) as number;
		const zero = DIGIT_ZEROS.find( ( from ) => code >= from && code <= from + 9 );

		return zero === undefined ? digit : String( code - zero );
	} );
}

const yearMarkersByLanguage = new Map<string, string[]>();

/**
 * The words a language writes beside a year (`г.` in Russian, `g.` in Latvian): the words its
 * month-and-year form has for every month that its month names do not.
 */
function yearMarkers( language: string ): string[] {
	const cached = yearMarkersByLanguage.get( language );

	if ( cached !== undefined ) {
		return cached;
	}

	const monthAndYear = dateTimeFormat( language, { calendar: 'gregory', month: 'long', year: 'numeric', timeZone: 'UTC' } );
	const nameTokens = [ 'long', 'short' ].flatMap( ( style ) => {
		const format = dateTimeFormat( language, { calendar: 'gregory', month: style as 'long' | 'short', timeZone: 'UTC' } );

		return months().flatMap( ( month ) => tokensOf( lowerCased( format.format( month ), language ) ) );
	} );
	const markers = months()
		.map( ( month ) => tokensOf( lowerCased( monthAndYear.format( month ), language ) ).filter( ( token ) => /\p{L}/u.test( token ) ) )
		.reduce( ( common, tokens ) => common.filter( ( token ) => tokens.includes( token ) ) )
		.filter( ( token ) => !nameTokens.includes( token ) );

	yearMarkersByLanguage.set( language, markers );

	return markers;
}

function months(): Date[] {
	return Array.from( { length: 12 }, ( _, index ) => new Date( Date.UTC( 2000, index, 15 ) ) );
}

// `1984 г.` is the year 1984; so is `1984-ж.`, once the marker and what joined it are gone.
function withoutYearMarker( text: string, language: string ): string {
	for ( const marker of yearMarkers( language ) ) {
		for ( const suffix of [ marker + '.', marker ] ) {
			if ( text.length > suffix.length && text.endsWith( suffix ) ) {
				return text.slice( 0, -suffix.length ).replace( /[\s.-]+$/, '' );
			}
		}
	}

	return text;
}

/**
 * The readings a text has, most likely first, or null when it has none.
 */
function partsOf( text: string, language: string ): DateParts[] | null {
	let match: RegExpMatchArray | null;

	// eslint-disable-next-line security/detect-unsafe-regex
	if ( ( match = text.match( /^(\d{3,4})(?:[-/.](\d{1,2})(?:[-/.](\d{1,2}))?)?\.?$/ ) ) !== null ) {
		return [ parts( match[ 1 ], match[ 2 ], match[ 3 ] ) ];
	}

	// eslint-disable-next-line security/detect-unsafe-regex
	if ( ( match = text.match( /^(\d{3,4}) ?[年년](?: ?(\d{1,2}) ?[月월](?: ?(\d{1,2}) ?[日일])?)?$/ ) ) !== null ) {
		return [ parts( match[ 1 ], match[ 2 ], match[ 3 ] ) ];
	}

	if ( ( match = text.match( /^(\d{1,2})[/.-](\d{3,4})$/ ) ) !== null ) {
		return [ parts( match[ 2 ], match[ 1 ], undefined ) ];
	}

	if ( ( match = text.match( /^(\d{1,2})\. ?(\d{1,2})\. ?(\d{3,4})\.?$/ ) ) !== null ) {
		return [ parts( match[ 3 ], match[ 2 ], match[ 1 ] ) ];
	}

	if ( ( match = text.match( /^(\d{1,2})[/-](\d{1,2})[/-](\d{3,4})$/ ) ) !== null ) {
		return slashedReadings( match[ 1 ], match[ 2 ], match[ 3 ] );
	}

	return namedMonthParts( text, language );
}

function slashedReadings( a: string, b: string, year: string ): DateParts[] {
	const dayFirst = parts( year, b, a );
	const monthFirst = parts( year, a, b );

	if ( Number( a ) > 12 ) {
		return [ dayFirst ];
	}

	if ( Number( b ) > 12 ) {
		return [ monthFirst ];
	}

	return [ dayFirst, monthFirst ];
}

// Spaces, the commas of the Latin, Arabic and CJK scripts, and the separators of numeric
// dates, which by the time names are looked at have failed to be one: `15-jun-1984`.
const SEPARATORS = /[\s,،、\-./]+/;

function tokensOf( text: string ): string[] {
	return text.split( SEPARATORS ).filter( ( token ) => token !== '' );
}

// A day, with the ordinal suffix of English, French, Dutch or Spanish or without one.
const DAY_TOKEN = /^(\d{1,2})(?:st|nd|rd|th|er|e|º|ª|°)?$/;

/**
 * A month name among the words, with a year and maybe a day among the numbers. Names of up to
 * three words are tried longest first, so `thg 6` wins over `6`. Words left over once the
 * name is taken out (`de`, `г.`) are ignored; a number of no known shape is not, since it may
 * be a day written in a way that is not understood, and dropping it would lose the day.
 */
function namedMonthParts( text: string, language: string ): DateParts[] | null {
	const tokens = tokensOf( text );
	const names = monthNames( language );
	let month: number | null = null;
	let rest = tokens;

	for ( let start = 0; start < tokens.length && month === null; start++ ) {
		for ( const length of [ 3, 2, 1 ] ) {
			const candidate = tokens.slice( start, start + length ).join( ' ' );
			const found = names.find( ( entry ) => entry.name === candidate );

			if ( found !== undefined ) {
				month = found.month;
				rest = [ ...tokens.slice( 0, start ), ...tokens.slice( start + length ) ];
				break;
			}
		}
	}

	if ( month === null ) {
		return null;
	}

	const years: number[] = [];
	const days: number[] = [];

	for ( const token of rest ) {
		const day = DAY_TOKEN.exec( token );

		if ( day !== null ) {
			days.push( Number( day[ 1 ] ) );
		} else if ( /^\d{3,4}$/.test( token ) ) {
			years.push( Number( token ) );
		} else if ( /\d/.test( token ) ) {
			return null;
		}
	}

	if ( years.length !== 1 || days.length > 1 ) {
		return null;
	}

	return [ { year: years[ 0 ], month, day: days.length === 1 ? days[ 0 ] : null } ];
}

function parts( year: string, month: string | undefined, day: string | undefined ): DateParts {
	return {
		year: Number( year ),
		month: month === undefined ? null : Number( month ),
		day: day === undefined ? null : Number( day ),
	};
}

const monthNamesByLanguage = new Map<string, { name: string; month: number }[]>();

/**
 * Every way `Intl` writes each month in the language and in English: long and short, on its own
 * and inside a date with a day or a year, where some languages inflect it (`czerwca`), prefix
 * it (`ביוני`) or use another case (`Ιούνιος` beside a year, `Ιουνίου` alone). Tokenized as
 * the text is, without the day, the year and the year marker.
 */
function monthNames( language: string ): { name: string; month: number }[] {
	const cached = monthNamesByLanguage.get( language );

	if ( cached !== undefined ) {
		return cached;
	}

	const names: { name: string; month: number }[] = [];
	const markerTokens = yearMarkers( language );
	const add = ( formatted: string, month: number, locale: string ): void => {
		const name = tokensOf( lowerCased( asciiDigits( formatted ), locale ).replace( /15|2000/g, ' ' ) )
			.filter( ( token ) => !markerTokens.includes( token ) )
			.join( ' ' );

		if ( /\p{L}/u.test( name ) && !names.some( ( entry ) => entry.name === name ) ) {
			names.push( { name, month } );
		}
	};

	for ( const locale of [ language, 'en', 'en-gb' ] ) {
		for ( const style of [ 'long', 'short' ] as const ) {
			const formats = [
				dateTimeFormat( locale, { calendar: 'gregory', month: style, timeZone: 'UTC' } ),
				dateTimeFormat( locale, { calendar: 'gregory', month: style, day: 'numeric', timeZone: 'UTC' } ),
				dateTimeFormat( locale, { calendar: 'gregory', month: style, year: 'numeric', timeZone: 'UTC' } ),
			];

			months().forEach( ( fifteenth, index ) => {
				for ( const format of formats ) {
					add( format.format( fifteenth ), index + 1, locale );
				}
			} );
		}
	}

	monthNamesByLanguage.set( language, names );

	return names;
}

function checked( date: DateParts ): DateTextReading {
	if ( date.year === 0 ) {
		return { kind: 'unreadable', problem: 'year-zero' };
	}

	if ( date.month !== null && ( date.month < 1 || date.month > 12 ) ) {
		return { kind: 'unreadable', problem: 'invalid-month' };
	}

	if ( date.day !== null ) {
		const daysInMonth = lastDayOf( date.year, date.month as number );

		if ( date.day < 1 || date.day > daysInMonth ) {
			return { kind: 'unreadable', problem: 'invalid-day', daysInMonth };
		}
	}

	const iso = [
		String( date.year ).padStart( 4, '0' ),
		...( date.month === null ? [] : [ String( date.month ).padStart( 2, '0' ) ] ),
		...( date.day === null ? [] : [ String( date.day ).padStart( 2, '0' ) ] ),
	].join( '-' );

	return parsePartialDate( iso ) === null ? { kind: 'unreadable', problem: 'not-a-date' } : { kind: 'date', iso };
}

function lastDayOf( year: number, month: number ): number {
	const lastOfMonth = new Date( 0 );
	lastOfMonth.setUTCFullYear( year, month, 0 );

	return lastOfMonth.getUTCDate();
}
