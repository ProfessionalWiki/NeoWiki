import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import BaseMultiStringInput from '@/components/Value/BaseMultiStringInput.vue';
import { CdxField } from '@wikimedia/codex';
import { newStringValue } from '@neo/domain/Value';
import { newTextProperty } from '@neo/domain/propertyTypes/Text.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';

describe( 'BaseMultiStringInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	function createWrapper( props: Partial<InstanceType<typeof BaseMultiStringInput>['$props']> = {} ): VueWrapper {
		return createTestWrapper( BaseMultiStringInput, {
			modelValue: newStringValue( 'value1', 'value2' ),
			property: newTextProperty( { multiple: true } ),
			formatName: 'text',
			inputType: 'text',
			rootClass: 'test-field',
			...props
		} );
	}

	describe( 'rendering', () => {
		it( 'renders an input field for each value', () => {
			const wrapper = createWrapper();

			expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 2 );
			expect( wrapper.findAll( 'input' ) ).toHaveLength( 2 );
		} );

		it( 'renders add button when multiple values are allowed', () => {
			const wrapper = createWrapper();

			expect( wrapper.find( 'button.test-field__add-button' ).exists() ).toBe( true );
		} );

		it( 'does not render add button when multiple values are not allowed', () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { multiple: false } )
			} );

			expect( wrapper.find( 'button.test-field__add-button' ).exists() ).toBe( false );
		} );
	} );

	describe( 'validation', () => {
		it( 'shows error when duplicate values are entered and uniqueness is required', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { multiple: true, uniqueItems: true } )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( 'value1' );

			const fields = wrapper.findAllComponents( CdxField );
			expect( fields[ 0 ].props( 'status' ) ).toBe( 'success' );
			expect( fields[ 1 ].props( 'status' ) ).toBe( 'error' );
			expect( fields[ 1 ].props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-unique' );
		} );

		it( 'disables add button when any field has an error', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { multiple: true, uniqueItems: true } )
			} );

			const addButton = wrapper.find( 'button.test-field__add-button' );
			expect( addButton.attributes( 'disabled' ) ).toBeUndefined();

			await wrapper.findAll( 'input' )[ 1 ].setValue( 'value1' );

			expect( addButton.attributes( 'disabled' ) ).toBeDefined();
		} );

		it( 'disables add button when any field is empty', async () => {
			const wrapper = createWrapper();

			const addButton = wrapper.find( 'button.test-field__add-button' );
			expect( addButton.attributes( 'disabled' ) ).toBeUndefined();

			await wrapper.findAll( 'input' )[ 0 ].setValue( '' );

			expect( addButton.attributes( 'disabled' ) ).toBeDefined();
		} );
	} );

	describe( 'value modification', () => {
		it( 'adds a new empty field when add button is clicked', async () => {
			const wrapper = createWrapper( { modelValue: newStringValue( 'value1', 'value2' ) } );

			await wrapper.find( 'button.test-field__add-button' ).trigger( 'click' );

			expect( wrapper.findAll( 'input' ) ).toHaveLength( 3 );
			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();
			expect( emitted![ 0 ][ 0 ] ).toEqual( newStringValue( 'value1', 'value2', '' ) );
		} );

		it( 'removes field when delete button is clicked', async () => {
			const wrapper = createWrapper( { modelValue: newStringValue( 'value1', 'value2' ) } );

			await wrapper.findAll( 'button.delete-button' )[ 0 ].trigger( 'click' );

			expect( wrapper.findAll( 'input' ) ).toHaveLength( 1 );
			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();
			expect( emitted![ 0 ][ 0 ] ).toEqual( newStringValue( 'value1' ) );
		} );

		it( 'emits updated value when input changes', async () => {
			const wrapper = createWrapper( { modelValue: newStringValue( 'value1', 'value2' ) } );

			await wrapper.findAll( 'input' )[ 0 ].setValue( 'new-value' );

			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();
			expect( emitted![ 0 ][ 0 ] ).toEqual( newStringValue( 'new-value', 'value2' ) );
		} );
	} );
} );
