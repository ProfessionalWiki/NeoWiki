import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import BooleanDisplay from '@/components/Value/BooleanDisplay.vue';
import { newBooleanValue, newNumberValue, newStringValue, Value } from '@/domain/Value';
import { newBooleanProperty, BooleanProperty } from '@/domain/propertyTypes/Boolean';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';

describe( 'BooleanDisplay', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( key: string ) => ( {
				text: () => key === 'neowiki-boolean-true' ? 'Yes' : 'No',
				parse: () => key,
			} ) ),
		} );
	} );

	function createWrapper( value: Value, property: BooleanProperty = newBooleanProperty() ): VueWrapper {
		const props: ValueDisplayProps<BooleanProperty> = { value, property };
		return createTestWrapper( BooleanDisplay, props );
	}

	it( 'renders the configured "true" label for BooleanValue(true)', () => {
		const wrapper = createWrapper( newBooleanValue( true ) );

		expect( wrapper.text() ).toBe( 'Yes' );
	} );

	it( 'renders the configured "false" label for BooleanValue(false)', () => {
		const wrapper = createWrapper( newBooleanValue( false ) );

		expect( wrapper.text() ).toBe( 'No' );
	} );

	it( 'renders empty when given a string Value', () => {
		const wrapper = createWrapper( newStringValue( 'not a boolean' ) );

		expect( wrapper.text() ).toBe( '' );
	} );

	it( 'renders empty when given a number Value', () => {
		const wrapper = createWrapper( newNumberValue( 1 ) );

		expect( wrapper.text() ).toBe( '' );
	} );

	it( 'uses the i18n key neowiki-boolean-true for the true label', () => {
		createWrapper( newBooleanValue( true ) );

		expect( mw.message ).toHaveBeenCalledWith( 'neowiki-boolean-true' );
	} );

	it( 'uses the i18n key neowiki-boolean-false for the false label', () => {
		createWrapper( newBooleanValue( false ) );

		expect( mw.message ).toHaveBeenCalledWith( 'neowiki-boolean-false' );
	} );
} );
