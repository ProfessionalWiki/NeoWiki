import { describe, expect, it } from 'vitest';
import { dateTextOf, DateTextReading, formatDateForDisplay, readDateText } from '@/domain/propertyTypes/dateText';

const read = ( text: string, language = 'en' ): DateTextReading => readDateText( text, language );

describe( 'readDateText', () => {

	it( 'reads nothing from whitespace', () => {
		expect( read( '  ' ) ).toEqual( { kind: 'empty' } );
	} );

	it.each( [
		[ 'a year', '1984', '1984' ],
		[ 'a year and month', '1984-06', '1984-06' ],
		[ 'a full date', '1984-06-15', '1984-06-15' ],
		[ 'a short year', '984', '0984' ],
		[ 'unpadded ISO parts', '1984-6-5', '1984-06-05' ],
		[ 'year first with slashes', '1984/06/15', '1984-06-15' ],
		[ 'year first with dots', '1984.06.15', '1984-06-15' ],
		[ 'day first with dots', '15.6.1984', '1984-06-15' ],
		[ 'month and year with a slash', '6/1984', '1984-06' ],
		[ 'a month name and year', 'June 1984', '1984-06' ],
		[ 'an abbreviated month name', 'Jun 1984', '1984-06' ],
		[ 'a month name in any case', 'JUNE 1984', '1984-06' ],
		[ 'day, month name, year', '15 June 1984', '1984-06-15' ],
		[ 'month name, day, year', 'June 15, 1984', '1984-06-15' ],
		[ 'month name, day, year without the comma', 'Jun 15 1984', '1984-06-15' ],
		[ 'surrounding whitespace', '  1984-06  ', '1984-06' ],
		[ 'Arabic-Indic digits', '٢٠٢٠-٠٦', '2020-06' ],
		[ 'Persian digits', '۱۹۸۴', '1984' ],
		[ 'fullwidth digits', '２０２０', '2020' ],
		[ 'Bengali digits', '১৯৮৪-০৬', '1984-06' ],
		[ 'a day with its ordinal', '15th June 1984', '1984-06-15' ],
		[ 'month name, ordinal day, year', 'June 1st, 1984', '1984-06-01' ],
		[ 'the four-letter September', '15 Sept 1984', '1984-09-15' ],
		[ 'a spreadsheet form with dashes', '15-Jun-1984', '1984-06-15' ],
		[ 'a spreadsheet form with dots', '15.jun.1984', '1984-06-15' ],
		[ 'a month and year with a dot', '6.1984', '1984-06' ],
		[ 'a dashed day-first date', '15-06-1984', '1984-06-15' ],
		[ 'an invisible mark pasted along', '1984-06-15\u200e', '1984-06-15' ],
	] )( 'reads %s', ( _description: string, text: string, iso: string ) => {
		expect( read( text ) ).toEqual( { kind: 'date', iso } );
	} );

	it.each( [
		[ 'German, with a dotted day', '15. Juni 1984', 'de', '1984-06-15' ],
		[ 'Spanish, with its fillers', '15 de junio de 1984', 'es', '1984-06-15' ],
		[ 'Polish, with the month inflected', '15 czerwca 1984', 'pl', '1984-06-15' ],
		[ 'Russian, with its year marker', '15 июня 1984 г.', 'ru', '1984-06-15' ],
		[ 'Hungarian, year first with dots', '1984. jún. 15.', 'hu', '1984-06-15' ],
		[ 'Vietnamese, whose month name holds a number', '15 thg 6, 1984', 'vi', '1984-06-15' ],
		[ 'Hebrew, with the month prefixed', '15 ביוני 1984', 'he', '1984-06-15' ],
		[ 'Japanese', '1984年6月15日', 'ja', '1984-06-15' ],
		[ 'Korean, with spaces', '1984년 6월', 'ko', '1984-06' ],
		[ 'Czech, numeric with spaces', '15. 6. 1984', 'cs', '1984-06-15' ],
		[ 'French, with the ordinal first', '1er juin 1984', 'fr', '1984-06-01' ],
		[ 'Dutch, with an ordinal day', '2e juni 1984', 'nl', '1984-06-02' ],
		[ 'Russian, a year with its marker', '1984 г.', 'ru', '1984' ],
		[ 'Bulgarian, numeric with the year marker', '15.06.1984 г.', 'bg', '1984-06-15' ],
		[ 'Latvian, a year with its marker', '1984. g.', 'lv', '1984' ],
		[ 'Turkish, in upper case', '15 HAZİRAN 1984', 'tr', '1984-06-15' ],
		[ 'Greek, with the month in the nominative', 'Ιούνιος 1984', 'el', '1984-06' ],
		[ 'Urdu, with the Arabic comma', '15 جون، 1984', 'ur', '1984-06-15' ],
		[ 'Hindi, in Devanagari digits', '१५ जून १९८४', 'hi', '1984-06-15' ],
		[ 'Thai, with its dotted abbreviation', '15 มิ.ย. 1984', 'th', '1984-06-15' ],
	] )( 'reads %s', ( _description: string, text: string, language: string, iso: string ) => {
		expect( read( text, language ) ).toEqual( { kind: 'date', iso } );
	} );

	describe( 'reads back what the display writes', () => {
		const languages = [
			'en', 'de', 'fr', 'es', 'it', 'pt', 'nl', 'sv', 'fi', 'cs', 'pl', 'hu', 'ru', 'uk', 'el', 'tr',
			'he', 'ar', 'fa', 'hi', 'th', 'vi', 'id', 'ja', 'zh', 'ko',
		];
		const isos = [ '1984', '1984-06', '1984-06-15', '0984-02-29', '2000-12' ];

		it.each( languages.flatMap( ( language ) => isos.map( ( iso ) => [ language, iso ] ) ) )(
			'in %s, %s',
			( language: string, iso: string ) => {
				expect( read( formatDateForDisplay( iso, language ), language ) ).toEqual( { kind: 'date', iso } );
			},
		);
	} );

	it( 'reads a month name in the reader\'s language', () => {
		expect( read( 'juin 1984', 'fr' ) ).toEqual( { kind: 'date', iso: '1984-06' } );
	} );

	it( 'reads an English month name whatever the reader\'s language', () => {
		expect( read( 'June 1984', 'fr' ) ).toEqual( { kind: 'date', iso: '1984-06' } );
	} );

	it( 'reads a slashed date day first and offers the month-first reading', () => {
		expect( read( '06/07/1984' ) ).toEqual( { kind: 'date', iso: '1984-07-06', alternative: '1984-06-07' } );
	} );

	it( 'offers no alternative when only one reading is a date', () => {
		expect( read( '15/06/1984' ) ).toEqual( { kind: 'date', iso: '1984-06-15' } );
	} );

	it( 'reads a slashed date month first when the day cannot be a month', () => {
		expect( read( '06/15/1984' ) ).toEqual( { kind: 'date', iso: '1984-06-15' } );
	} );

	it( 'offers no alternative when both readings are the same day', () => {
		expect( read( '06/06/1984' ) ).toEqual( { kind: 'date', iso: '1984-06-06' } );
	} );

	it.each( [
		[ 'words', 'next tuesday' ],
		[ 'a two-digit year, whose century is a guess', '84' ],
		[ 'a negative year', '-0044' ],
		[ 'a five-digit year', '19840' ],
		[ 'a time', '1984-06-15T10:00' ],
		[ 'a month name alone', 'June' ],
	] )( 'cannot read %s', ( _description: string, text: string ) => {
		expect( read( text ) ).toEqual( { kind: 'unreadable', problem: 'not-a-date' } );
	} );

	it( 'rejects year zero', () => {
		expect( read( '0000-06' ) ).toEqual( { kind: 'unreadable', problem: 'year-zero' } );
	} );

	it.each( [ '1984-13', '13/13/1984', '1984-00' ] )( 'rejects the month in %s', ( text: string ) => {
		expect( read( text ) ).toEqual( { kind: 'unreadable', problem: 'invalid-month' } );
	} );

	it( 'rejects a day before the first', () => {
		expect( read( '0.6.1984' ) ).toEqual( { kind: 'unreadable', problem: 'invalid-day', daysInMonth: 30 } );
	} );

	it( 'rejects a day the month does not have, saying how many it has', () => {
		expect( read( '30.2.1985' ) ).toEqual( { kind: 'unreadable', problem: 'invalid-day', daysInMonth: 28 } );
	} );

	it( 'accepts the leap day of a leap year', () => {
		expect( read( '29.2.1984' ) ).toEqual( { kind: 'date', iso: '1984-02-29' } );
	} );

} );

describe( 'dateTextOf', () => {

	it( 'is the display form when that reads back as the date', () => {
		expect( dateTextOf( '1984-06-15', 'de' ) ).toBe( '15. Juni 1984' );
	} );

	it( 'is the stored form when the display form does not read back', () => {
		expect( dateTextOf( '1984-06-15', 'eu' ) ).toBe( '1984-06-15' );
	} );

} );

describe( 'formatDateForDisplay', () => {

	it( 'returns the raw input when the value cannot be parsed', () => {
		expect( formatDateForDisplay( 'not-a-date', 'en-US' ) ).toBe( 'not-a-date' );
	} );

	it( 'returns the raw input when given an empty string', () => {
		expect( formatDateForDisplay( '', 'en-US' ) ).toBe( '' );
	} );

	it( 'renders a parsed date as a non-ISO human-readable string', () => {
		const result = formatDateForDisplay( '2025-06-15', 'en-US' );

		expect( result ).not.toBe( '2025-06-15' );
		expect( result ).not.toMatch( /^\d{4}-\d{2}-\d{2}$/ );
	} );

	it( 'includes the year of the date', () => {
		expect( formatDateForDisplay( '2025-06-15', 'en-US' ) ).toContain( '2025' );
	} );

	it( 'renders the stored calendar day regardless of host timezone', () => {
		// Interpreted as UTC and rendered with timeZone: 'UTC', so the day
		// component is always 15 and never rolls to 14 or 16.
		expect( formatDateForDisplay( '2025-06-15', 'en-US' ) ).toContain( '15' );
	} );

	it( 'does not append a time component', () => {
		expect( formatDateForDisplay( '2025-06-15', 'en-US' ) ).not.toMatch( /\d{2}:\d{2}/ );
	} );

	it( 'renders a year and month without a day', () => {
		const result = formatDateForDisplay( '1984-06', 'en-US' );

		expect( result ).toContain( '1984' );
		expect( result ).not.toBe( '1984-06' );
		expect( result ).not.toMatch( /\b0?1\b/ );
	} );

	it( 'renders a year without a month or day', () => {
		expect( formatDateForDisplay( '1984', 'en-US' ) ).toBe( '1984' );
	} );

	it( 'renders a year of the Gregorian calendar in a language whose default calendar is another', () => {
		expect( formatDateForDisplay( '1984', 'fa' ) ).toBe( '۱۹۸۴' );
	} );

	it( 'falls back to English for a wiki language Intl does not know', () => {
		expect( formatDateForDisplay( '1984-06', 'not-a-language-tag-!' ) ).toBe( 'Jun 1984' );
	} );

} );
