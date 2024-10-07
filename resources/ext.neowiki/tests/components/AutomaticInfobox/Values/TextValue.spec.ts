import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import TextValue from '@/components/AutomaticInfobox/Values/TextValue.vue';
import { newNumberValue, newStringValue } from '@neo/domain/Value';

describe( 'TextValue', () => {
	it( 'renders a single text value correctly', () => {
		const wrapper = mount( TextValue, {
			props: {
				value: newStringValue( 'John Doe' )
			}
		} );

		expect( wrapper.text() ).toBe( 'John Doe' );
	} );

	it( 'renders multiple text values correctly', () => {
		const wrapper = mount( TextValue, {
			props: {
				value: newStringValue( 'John Doe', 'Johnny' )
			}
		} );

		expect( wrapper.text() ).toBe( 'John Doe, Johnny' );
	} );

	it( 'renders nothing when a single empty value is present', () => {

		const wrapper = mount( TextValue, {
			props: {
				value: newStringValue( '' )
			}
		} );

		expect( wrapper.text() ).toBe( '' );
	} );

	it( 'skips empty values when rendering multiple values', () => {
		const wrapper = mount( TextValue, {
			props: {
				value: newStringValue( 'John', '', 'Doe', '', 'Johnny' )
			}
		} );

		expect( wrapper.text() ).toBe( 'John, Doe, Johnny' );
	} );

	it( 'renders nothing when all values are empty', () => {
		const wrapper = mount( TextValue, {
			props: {
				value: newStringValue( '', '', '' )
			}
		} );

		expect( wrapper.text() ).toBe( '' );
	} );

	it( 'returns empty string for wrong value type', () => {
		const wrapper = mount( TextValue, {
			props: {
				value: newNumberValue( 42 )
			}
		} );
		expect( wrapper.text() ).toBe( '' );
	} );
} );
