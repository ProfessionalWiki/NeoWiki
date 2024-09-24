import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import NumberValue from '@/components/AutomaticInfobox/Values/NumberValue.vue';
import { Statement } from '@neo/domain/Statement';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { NumberFormat } from '@neo/domain/valueFormats/Number';
import { newNumberValue } from '@neo/domain/Value';

describe( 'NumberValue', () => {
	it( 'renders the number value correctly', () => {
		const statement = new Statement(
			new PropertyName( 'age' ),
			NumberFormat.formatName,
			newNumberValue( 30 )
		);

		const wrapper = mount( NumberValue, {
			props: { statement }
		} );

		expect( wrapper.text() ).toBe( '30' );
	} );

	it( 'handles decimal numbers', () => {
		const statement = new Statement(
			new PropertyName( 'price' ),
			NumberFormat.formatName,
			newNumberValue( 19.99 )
		);

		const wrapper = mount( NumberValue, {
			props: { statement }
		} );

		expect( wrapper.text() ).toBe( '19.99' );
	} );

	it( 'handles negative numbers', () => {
		const statement = new Statement(
			new PropertyName( 'temperature' ),
			NumberFormat.formatName,
			newNumberValue( -5 )
		);

		const wrapper = mount( NumberValue, {
			props: { statement }
		} );

		expect( wrapper.text() ).toBe( '-5' );
	} );
} );
