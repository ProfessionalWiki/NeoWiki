import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField } from '@wikimedia/codex';
import { newNumberValue } from '@/domain/Value';
import NumberInput from '@/components/Value/NumberInput.vue';
import { newNumberProperty, NumberProperty } from '@/domain/propertyTypes/Number';
import { ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';

describe( 'NumberInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	function newWrapper( props: Partial<ValueInputProps<NumberProperty>> = {} ): VueWrapper {
		return createTestWrapper( NumberInput, {
			modelValue: newNumberValue( 10 ),
			label: 'Test Label',
			property: newNumberProperty( {} ),
			...props
		} );
	}

	it( 'renders correctly', () => {
		const wrapper = newWrapper();

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual( {} );
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'validates maxValue for the number', async () => {
		const wrapper = newWrapper( {
			property: newNumberProperty( { minimum: 42, maximum: 50 } )
		} );

		await wrapper.find( 'input' ).setValue( 51 );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-max-value' );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns initial value', () => {
			const wrapper = newWrapper( {
				modelValue: newNumberValue( 42 )
			} );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toEqual( newNumberValue( 42 ) );
		} );

		it( 'returns updated value after input', async () => {
			const wrapper = newWrapper( {
				modelValue: newNumberValue( 10 )
			} );

			await wrapper.find( 'input' ).setValue( '99' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toEqual( newNumberValue( 99 ) );
		} );

		it( 'returns undefined for empty input', async () => {
			const wrapper = newWrapper( {
				modelValue: newNumberValue( 10 )
			} );

			await wrapper.find( 'input' ).setValue( '' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toBeUndefined();
		} );

		it( 'returns undefined for non-numeric input', async () => {
			const wrapper = newWrapper( {
				modelValue: newNumberValue( 10 )
			} );

			await wrapper.find( 'input' ).setValue( 'abc' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toBeUndefined();
		} );
	} );
} );
