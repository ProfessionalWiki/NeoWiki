import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField } from '@wikimedia/codex';
import { newNumberValue, Value } from '@neo/domain/Value';
import NumberInput from '@/components/Value/NumberInput.vue';
import { newNumberProperty, NumberProperty } from '@neo/domain/valueFormats/Number';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';

// TODO: move to sane place
export interface InputComponentProps<T extends PropertyDefinition = PropertyDefinition> {
	modelValue: Value;
	label: string;
	property: T;
}

type NumberInputProps = InputComponentProps<NumberProperty>;

describe( 'NumberInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	function newWrapper( props: Partial<NumberInputProps> = {} ): VueWrapper {
		return mount( NumberInput, {
			props: {
				modelValue: newNumberValue( 10 ),
				label: 'Test Label',
				property: newNumberProperty( {} ),
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
		const wrapper = newWrapper( { property: newNumberProperty( { required: true } ) } );

		await wrapper.find( 'input' ).setValue( '' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'validates maxValue for the number', async () => {
		const wrapper = newWrapper( {
			property: newNumberProperty( { minimum: 42, maximum: 50 } )
		} );

		await wrapper.find( 'input' ).setValue( 51 );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-max-value' );
	} );

	it( 'validates minValue for the number', async () => {
		const wrapper = newWrapper( {
			property: newNumberProperty( { minimum: 42, maximum: 50 } )
		} );

		await wrapper.find( 'input' ).setValue( 41 );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-min-value' );
	} );

	it( 'emits valid field when value within min and max', async () => {
		const wrapper = newWrapper( {
			property: newNumberProperty( { minimum: 42, maximum: 42 } )
		} );

		await wrapper.find( 'input' ).setValue( 42 );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ true ] );
	} );

	it( 'emits invalid field when validation fails', async () => {
		const wrapper = newWrapper( {
			property: newNumberProperty( { minimum: 42, maximum: 42 } )
		} );

		await wrapper.find( 'input' ).setValue( 43 );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ false ] );
	} );
} );
