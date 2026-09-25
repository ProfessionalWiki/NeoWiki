import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import DateDisplay from '@/components/Value/DateDisplay.vue';
import { newNumberValue, newStringValue, Value } from '@/domain/Value';
import { newDateProperty, DateProperty } from '@/domain/propertyTypes/Date';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers.ts';

function createWrapper( props: Partial<ValueDisplayProps<DateProperty>> ): VueWrapper {
	const defaultProps: ValueDisplayProps<DateProperty> = {
		value: newStringValue( '' ),
		property: newDateProperty(),
	};

	return createTestWrapper( DateDisplay, {
		...defaultProps,
		...props,
	} );
}

function createWrapperWithValue( value: Value ): VueWrapper {
	return createWrapper( { value } );
}

describe( 'DateDisplay', () => {
	beforeEach( () => {
		setupMwMock( { config: { wgUserLanguage: 'en' }, functions: [ 'config', 'language' ] } );
	} );

	it( 'renders the date in the user\'s language', () => {
		setupMwMock( { config: { wgUserLanguage: 'de' }, functions: [ 'config', 'language' ] } );

		expect( createWrapperWithValue( newStringValue( '1984-06-15' ) ).text() ).toBe( '15. Juni 1984' );
	} );

	describe( 'valid ISO 8601 date input', () => {
		it( 'renders a <time> element with the raw date string as the datetime attribute', () => {
			const date = '2025-06-15';
			const wrapper = createWrapperWithValue( newStringValue( date ) );

			const time = wrapper.find( 'time' );
			expect( time.exists() ).toBe( true );
			expect( time.attributes( 'datetime' ) ).toBe( date );
		} );

		it( 'renders the date formatted for display, not as the raw ISO string', () => {
			const wrapper = createWrapperWithValue( newStringValue( '2025-06-15' ) );

			expect( wrapper.text() ).not.toBe( '2025-06-15' );
			expect( wrapper.text() ).toContain( '2025' );
		} );

		it( 'renders the stored calendar day regardless of host timezone', () => {
			const wrapper = createWrapperWithValue( newStringValue( '2025-06-15' ) );

			expect( wrapper.text() ).toContain( '15' );
		} );

		it.each( [ '1984', '1984-06' ] )( 'renders %s in a <time> element carrying it unchanged', ( date: string ) => {
			const time = createWrapperWithValue( newStringValue( date ) ).find( 'time' );

			expect( time.exists() ).toBe( true );
			expect( time.attributes( 'datetime' ) ).toBe( date );
		} );

		it( 'renders a year and month formatted for display', () => {
			const wrapper = createWrapperWithValue( newStringValue( '1984-06' ) );

			expect( wrapper.text() ).not.toBe( '1984-06' );
			expect( wrapper.text() ).toContain( '1984' );
		} );

		it( 'does not render a time component', () => {
			const wrapper = createWrapperWithValue( newStringValue( '2025-06-15' ) );

			expect( wrapper.text() ).not.toMatch( /\d{2}:\d{2}/ );
		} );
	} );

	describe( 'invalid input', () => {
		it( 'renders a span (not a time element) with the raw string when the value cannot be parsed', () => {
			const wrapper = createWrapperWithValue( newStringValue( 'not-a-date' ) );

			expect( wrapper.find( 'time' ).exists() ).toBe( false );
			expect( wrapper.find( 'span' ).exists() ).toBe( true );
			expect( wrapper.text() ).toBe( 'not-a-date' );
		} );

		it( 'renders a span (not a time element) when the value carries a time component', () => {
			const wrapper = createWrapperWithValue( newStringValue( '2025-06-15T12:00:00Z' ) );

			expect( wrapper.find( 'time' ).exists() ).toBe( false );
			expect( wrapper.text() ).toBe( '2025-06-15T12:00:00Z' );
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
