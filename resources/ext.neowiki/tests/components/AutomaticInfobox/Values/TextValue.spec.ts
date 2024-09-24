import { mount } from '@vue/test-utils';
import { describe, it, expect } from 'vitest';
import TextValue from '@/components/AutomaticInfobox/Values/TextValue.vue';
import { Statement } from '@neo/domain/Statement';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { TextFormat } from '@neo/domain/valueFormats/Text';
import { newStringValue } from '@neo/domain/Value';

describe( 'TextValue', () => {
	it( 'renders a single text value correctly', () => {
		const statement = new Statement(
			new PropertyName( 'name' ),
			TextFormat.formatName,
			newStringValue( 'John Doe' )
		);

		const wrapper = mount( TextValue, {
			props: { statement }
		} );

		expect( wrapper.text() ).toBe( 'John Doe' );
	} );

	it( 'renders multiple text values correctly', () => {
		const statement = new Statement(
			new PropertyName( 'aliases' ),
			TextFormat.formatName,
			newStringValue( 'John Doe', 'Johnny' )
		);

		const wrapper = mount( TextValue, {
			props: { statement }
		} );

		expect( wrapper.text() ).toBe( 'John Doe, Johnny' );
	} );

	it( 'renders nothing when a single empty value is present', () => {
		const statement = new Statement(
			new PropertyName( 'description' ),
			TextFormat.formatName,
			newStringValue( '' )
		);

		const wrapper = mount( TextValue, {
			props: { statement }
		} );

		expect( wrapper.text() ).toBe( '' );
	} );

	it( 'skips empty values when rendering multiple values', () => {
		const statement = new Statement(
			new PropertyName( 'aliases' ),
			TextFormat.formatName,
			newStringValue( 'John', '', 'Doe', '', 'Johnny' )
		);

		const wrapper = mount( TextValue, {
			props: { statement }
		} );

		expect( wrapper.text() ).toBe( 'John, Doe, Johnny' );
	} );

	it( 'renders nothing when all values are empty', () => {
		const statement = new Statement(
			new PropertyName( 'aliases' ),
			TextFormat.formatName,
			newStringValue( '', '', '' )
		);

		const wrapper = mount( TextValue, {
			props: { statement }
		} );

		expect( wrapper.text() ).toBe( '' );
	} );
} );
