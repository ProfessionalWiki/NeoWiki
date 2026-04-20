import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import DateTimeDisplay from '@/components/Value/DateTimeDisplay.vue';
import { newNumberValue, newStringValue, Value } from '@/domain/Value';
import { newDateTimeProperty, DateTimeProperty } from '@/domain/propertyTypes/DateTime';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

function createWrapper( props: Partial<ValueDisplayProps<DateTimeProperty>> ): ReturnType<typeof mount> {
	const defaultProps: ValueDisplayProps<DateTimeProperty> = {
		value: newStringValue( '' ),
		property: newDateTimeProperty(),
	};

	return mount( DateTimeDisplay, {
		props: {
			...defaultProps,
			...props,
		},
	} );
}

function createWrapperWithValue( value: Value ): ReturnType<typeof mount> {
	return createWrapper( { value } );
}

describe( 'DateTimeDisplay', () => {
	describe( 'valid ISO 8601 input', () => {
		it( 'renders a <time> element with the raw ISO string as the datetime attribute', () => {
			const iso = '2025-06-15T12:00:00Z';
			const wrapper = createWrapperWithValue( newStringValue( iso ) );

			const time = wrapper.find( 'time' );
			expect( time.exists() ).toBe( true );
			expect( time.attributes( 'datetime' ) ).toBe( iso );
		} );

		it( 'renders the instant formatted in the host timezone', () => {
			const iso = '2025-06-15T12:00:00Z';
			const wrapper = createWrapperWithValue( newStringValue( iso ) );

			const localHour = String( new Date( iso ).getHours() ).padStart( 2, '0' );
			expect( wrapper.text() ).toContain( localHour );
		} );

		it( 'includes a timezone name suffix in the rendered text', () => {
			const wrapper = createWrapperWithValue( newStringValue( '2025-06-15T12:00:00Z' ) );

			// With timeZoneName: 'short', toLocaleString appends a TZ abbreviation
			// (e.g. "GMT", "UTC", "CEST", "PST"). Asserting on a letter-containing
			// suffix after the formatted time is TZ- and locale-invariant.
			expect( wrapper.text() ).toMatch( /[A-Za-z]{2,}/ );
		} );

		it( 'preserves an explicit-offset ISO string as the datetime attribute', () => {
			const iso = '2025-06-15T23:30:00+05:00';
			const wrapper = createWrapperWithValue( newStringValue( iso ) );

			expect( wrapper.find( 'time' ).attributes( 'datetime' ) ).toBe( iso );
		} );
	} );

	describe( 'invalid input', () => {
		it( 'renders a span (not a time element) with the raw string when the value cannot be parsed', () => {
			const wrapper = createWrapperWithValue( newStringValue( 'not-a-date' ) );

			expect( wrapper.find( 'time' ).exists() ).toBe( false );
			expect( wrapper.find( 'span' ).exists() ).toBe( true );
			expect( wrapper.text() ).toBe( 'not-a-date' );
		} );

		it( 'renders an empty span when the string value is empty', () => {
			const wrapper = createWrapperWithValue( newStringValue( '' ) );

			expect( wrapper.find( 'time' ).exists() ).toBe( false );
			expect( wrapper.text() ).toBe( '' );
		} );

		it( 'renders an empty span when given the wrong value type', () => {
			const wrapper = createWrapperWithValue( newNumberValue( 42 ) );

			expect( wrapper.find( 'time' ).exists() ).toBe( false );
			expect( wrapper.text() ).toBe( '' );
		} );
	} );
} );
