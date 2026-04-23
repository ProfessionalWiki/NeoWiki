import { describe, expect, it } from 'vitest';
import { formatDateTimeForDisplay } from '@/domain/propertyTypes/dateTimeFormat';

describe( 'formatDateTimeForDisplay', () => {
	it( 'returns the raw input when the ISO cannot be parsed', () => {
		expect( formatDateTimeForDisplay( 'not-a-date' ) ).toBe( 'not-a-date' );
	} );

	it( 'returns the raw input when given an empty string', () => {
		expect( formatDateTimeForDisplay( '' ) ).toBe( '' );
	} );

	it( 'renders a parsed ISO as a non-ISO human-readable string', () => {
		const result = formatDateTimeForDisplay( '2025-06-15T12:00:00Z' );

		expect( result ).not.toBe( '2025-06-15T12:00:00Z' );
		expect( result ).not.toMatch( /Z$/ );
		expect( result ).not.toMatch( /\d{4}-\d{2}-\d{2}T/ );
	} );

	it( 'includes the year of the parsed instant', () => {
		expect( formatDateTimeForDisplay( '2025-06-15T12:00:00Z' ) ).toContain( '2025' );
	} );

	it( 'includes a timezone abbreviation in the rendered text', () => {
		// With timeZoneName: 'short', toLocaleString appends a TZ abbreviation
		// (UTC, CEST, PDT, MEZ, ...) or a GMT±HH:MM offset. Both are 3+
		// consecutive uppercase letters, which won't match "Jun" (mixed case)
		// or AM/PM (2 letters).
		expect( formatDateTimeForDisplay( '2025-06-15T12:00:00Z' ) ).toMatch( /[A-Z]{3,}/ );
	} );
} );
