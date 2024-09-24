import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import NumberValue from '@/components/AutomaticInfobox/Values/NumberValue.vue';
import { newNumberValue } from '@neo/domain/Value';

describe( 'NumberValue', () => {
	it( 'renders the number value correctly', () => {
		const wrapper = mount( NumberValue, {
			props: {
				value: newNumberValue( 30 )
			}
		} );

		expect( wrapper.text() ).toBe( '30' );
	} );

	it( 'handles decimal numbers', () => {
		const wrapper = mount( NumberValue, {
			props: {
				value: newNumberValue( 19.99 )
			}
		} );

		expect( wrapper.text() ).toBe( '19.99' );
	} );

	it( 'handles negative numbers', () => {
		const wrapper = mount( NumberValue, {
			props: {
				value: newNumberValue( -5 )
			}
		} );

		expect( wrapper.text() ).toBe( '-5' );
	} );
} );
