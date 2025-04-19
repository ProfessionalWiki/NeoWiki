import { mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import { newNumberValue, newStringValue, Value } from '@neo/domain/Value';
import { newTextProperty } from '@neo/domain/propertyTypes/Text.ts';
import TextDisplay from '@/components/Value/TextDisplay.vue';

function createWrapper( ...parts: string[] ): ReturnType<typeof mount> {
	return createWrapperWithValue( newStringValue( parts ) );
}

function createWrapperWithValue( value: Value ): ReturnType<typeof mount> {
	return mount( TextDisplay, {
		props: {
			value: value,
			property: newTextProperty()
		}
	} );
}

describe( 'TextDisplay', () => {
	it( 'renders a single text value correctly', () => {
		const wrapper = createWrapper( 'John Doe' );

		expect( wrapper.text() ).toBe( 'John Doe' );
	} );

	it( 'renders multiple text values correctly', () => {
		const wrapper = createWrapper( 'John Doe', 'Johnny' );

		expect( wrapper.text() ).toBe( 'John Doe, Johnny' );
	} );

	it( 'renders nothing when there are no value parts', () => {
		const wrapper = createWrapper();

		expect( wrapper.text() ).toBe( '' );
	} );

	it( 'skips empty values when rendering multiple values', () => {
		const wrapper = createWrapper( ' ', 'John', '', 'Doe', '  ', 'Johnny', ' ' );

		expect( wrapper.text() ).toBe( 'John, Doe, Johnny' );
	} );

	it( 'renders nothing when given a Value of incorrect type', () => {
		const wrapper = createWrapperWithValue( newNumberValue( 42 ) );
		expect( wrapper.text() ).toBe( '' );
	} );
} );
