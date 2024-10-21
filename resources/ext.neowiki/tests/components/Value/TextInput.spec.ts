import { mount, VueWrapper } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TextInput from '@/components/Value/TextInput.vue';
import { CdxField } from '@wikimedia/codex';
import { newStringValue, Value } from '@neo/domain/Value';
import { newTextProperty, TextProperty } from '@neo/domain/valueFormats/Text';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';

// TODO: move to sane place
export interface InputComponentProps<T extends PropertyDefinition = PropertyDefinition> {
	modelValue: Value;
	label: string;
	property: T;
}

type TextInputProps = InputComponentProps<TextProperty>;

describe( 'TextInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	function newWrapper( props: Partial<TextInputProps> = {} ): VueWrapper {
		return mount( TextInput, {
			props: {
				modelValue: newStringValue( 'Test' ),
				label: 'Test Label',
				property: newTextProperty( {} ),
				...props
			}
		} );
	}

	it( 'renders correctly', () => {
		const wrapper = newWrapper();

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'validates required field', async () => { // TODO: also test non-required field
		const wrapper = newWrapper( { property: newTextProperty( { required: true } ) } );

		await wrapper.find( 'input' ).setValue( '' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'fails validation when maxLength is exceeded', async () => { // TODO: also test success (including boundary condition)
		const wrapper = newWrapper( {
			property: newTextProperty( { maxLength: 10 } )
		} );

		await wrapper.find( 'input' ).setValue( '01234567890' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-max-length' );
	} );

	it( 'fails validation when minLength is not reached', async () => { // TODO: also test success (including boundary condition)
		const wrapper = newWrapper( {
			property: newTextProperty( { minLength: 5 } )
		} );

		await wrapper.find( 'input' ).setValue( '1234' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-min-length' );
	} );

	it( 'emits valid field', async () => {
		const wrapper = newWrapper();

		await wrapper.find( 'input' ).setValue( 'Valid Text' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ true ] );
	} );

	it( 'emits invalid field when validation fails', async () => {
		const wrapper = newWrapper( {
			property: newTextProperty( { minLength: 2 } )
		} );

		await wrapper.find( 'input' ).setValue( 'a' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ false ] );
	} );
} );
