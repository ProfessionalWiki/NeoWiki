import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import NumberValue from '@/components/AutomaticInfobox/Values/NumberValue.vue';
import { newNumberValue } from '@neo/domain/Value';
import { NumberProperty } from '@neo/domain/valueFormats/Number';

describe( 'NumberValue', () => {
	it( 'handles integers without precision', () => {
		const wrapper = mount( NumberValue, {
			props: {
				value: newNumberValue( 30 ),
				property: {} as NumberProperty
			}
		} );

		expect( wrapper.text() ).toBe( '30' );
	} );

	it( 'handles decimal numbers without precision', () => {
		const wrapper = mount( NumberValue, {
			props: {
				value: newNumberValue( 19.99 ),
				property: {} as NumberProperty
			}
		} );

		expect( wrapper.text() ).toBe( '19.99' );
	} );

	it( 'handles negative numbers without precision', () => {
		const wrapper = mount( NumberValue, {
			props: {
				value: newNumberValue( -5 ),
				property: {} as NumberProperty
			}
		} );

		expect( wrapper.text() ).toBe( '-5' );
	} );

	it( 'applies precision when specified', () => {
		const wrapper = mount( NumberValue, {
			props: {
				value: newNumberValue( 3.14159 ),
				property: { precision: 2 } as NumberProperty
			}
		} );

		expect( wrapper.text() ).toBe( '3.14' );
	} );

	it( 'handles zero precision', () => {
		const wrapper = mount( NumberValue, {
			props: {
				value: newNumberValue( 3.14159 ),
				property: { precision: 0 } as NumberProperty
			}
		} );

		expect( wrapper.text() ).toBe( '3' );
	} );

	it( 'handles high precision', () => {
		const wrapper = mount( NumberValue, {
			props: {
				value: newNumberValue( 3.14 ),
				property: { precision: 5 } as NumberProperty
			}
		} );

		expect( wrapper.text() ).toBe( '3.14000' );
	} );
} );
