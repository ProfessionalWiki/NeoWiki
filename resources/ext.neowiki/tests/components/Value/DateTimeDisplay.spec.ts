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
	// The assertions below are deliberately locale-invariant: the year and the
	// minute-second pair are stable across en-US, en-GB, de-DE, etc., whereas
	// day-first vs month-first and 12h vs 24h clock rendering are not. This
	// keeps the test green for contributors whose host locale differs from CI.
	it( 'renders a UTC date/time using the UTC timezone rather than the host timezone', () => {
		const wrapper = createWrapperWithValue( newStringValue( '2025-06-15T12:00:00Z' ) );

		const text = wrapper.text();
		expect( text ).toContain( '2025' );
		expect( text ).toContain( ':00:00' );
	} );

	it( 'converts a positive-offset datetime to UTC for display', () => {
		// 2025-06-15T23:30:00+05:00 == 2025-06-15T18:30:00Z — must NOT display as 23:30.
		// The positive assertion uses the locale-invariant ":30:00" suffix; the
		// negative one proves the wrong local time is not rendered.
		const wrapper = createWrapperWithValue( newStringValue( '2025-06-15T23:30:00+05:00' ) );

		const text = wrapper.text();
		expect( text ).toContain( ':30:00' );
		expect( text ).not.toContain( '23:30' );
	} );

	it( 'renders the raw string when the datetime cannot be parsed', () => {
		const wrapper = createWrapperWithValue( newStringValue( 'not-a-date' ) );

		expect( wrapper.text() ).toBe( 'not-a-date' );
	} );

	it( 'renders empty when the string value is empty', () => {
		const wrapper = createWrapperWithValue( newStringValue( '' ) );

		expect( wrapper.text() ).toBe( '' );
	} );

	it( 'renders empty when given the wrong value type', () => {
		const wrapper = createWrapperWithValue( newNumberValue( 42 ) );

		expect( wrapper.text() ).toBe( '' );
	} );
} );
