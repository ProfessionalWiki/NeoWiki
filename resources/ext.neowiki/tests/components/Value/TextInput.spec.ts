import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import TextInput from '@/components/Value/TextInput.vue';
import { CdxField, ValidationMessages, ValidationStatusType } from '@wikimedia/codex';
import { newStringValue } from '@neo/domain/Value';
import { newTextProperty } from '@neo/domain/propertyTypes/Text';
import { createTestWrapper } from '../../VueTestHelpers.ts';
import type { TextInputExposed } from '@/components/Value/TextInput.vue';

describe( 'TextInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	function createWrapper( props: Partial<InstanceType<typeof TextInput>['$props']> = {} ): VueWrapper<InstanceType<typeof TextInput>> {
		return createTestWrapper( TextInput, {
			modelValue: newStringValue( 'Test' ),
			label: 'Test Label',
			property: newTextProperty( { multiple: true } ),
			...props
		} );
	}

	const assertFieldStatus = (
		field: VueWrapper<InstanceType<typeof CdxField>>,
		expectedStatus: ValidationStatusType,
		expectedErrorMessage: ValidationMessages = {}
	): void => {
		expect( field.props( 'status' ) ).toBe( expectedStatus );
		expect( field.props( 'messages' ) ).toEqual( {
			...expectedErrorMessage
		} );
	};

	describe( 'Rendering component', () => {
		it( 'renders single text field correctly', () => {
			const wrapper = createWrapper();
			expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 1 );
			expect( wrapper.findAll( 'input' ) ).toHaveLength( 1 );
			expect( wrapper.text() ).toContain( 'Test Label' );
		} );

		it( 'renders multiple text fields correctly', () => {
			const wrapper = createWrapper( {
				modelValue: newStringValue( 'Text1', 'Text2' )
			} );
			expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 2 );
			expect( wrapper.findAll( 'input' ) ).toHaveLength( 2 );
		} );
	} );

	describe( 'Validation', () => {
		it( 'does not allow all empty fields when property value is required', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { required: true, multiple: true } ),
				modelValue: newStringValue( 'Text1', 'Text2' )
			} );
			await wrapper.findAll( 'input' )[ 0 ].setValue( '' );
			await wrapper.findAll( 'input' )[ 1 ].setValue( '' );

			const fields = wrapper.findAllComponents( CdxField );
			assertFieldStatus( fields[ 0 ], 'error', { error: 'neowiki-field-required' } );
		} );

		it( 'validates minimum length in multiple fields', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { minLength: 5 } ),
				modelValue: newStringValue( 'Text1', '12345' )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( '1234' );
			const fields = wrapper.findAllComponents( CdxField );
			assertFieldStatus( fields[ 1 ], 'error', { error: 'neowiki-field-min-length' } );
		} );

		it( 'validates maximum length in multiple fields', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { maxLength: 5 } ),
				modelValue: newStringValue( 'Text1', '12345' )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( '123456' );
			const fields = wrapper.findAllComponents( CdxField );
			assertFieldStatus( fields[ 1 ], 'error', { error: 'neowiki-field-max-length' } );
		} );

		it( 'allows empty input for optional single field', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { required: false } ),
				modelValue: newStringValue( '' )
			} );

			await wrapper.findAll( 'input' )[ 0 ].setValue( '' );

			const fields = wrapper.findAllComponents( CdxField );
			assertFieldStatus( fields[ 0 ], 'success' );
		} );

		it( 'shows error on duplicate when uniqueness is required', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { multiple: true, uniqueItems: true } ),
				modelValue: newStringValue( 'Text1', 'Text2', 'Text3' )
			} );

			await wrapper.findAll( 'input' )[ 0 ].setValue( 'Text2' );

			const fields = wrapper.findAllComponents( CdxField );
			expect( fields[ 1 ].props( 'status' ) ).toBe( 'error' );
			expect( fields[ 1 ].props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-unique' );
		} );
	} );

	describe( 'Event handling', () => {
		it( 'emits update:modelValue event when input changes', async () => {
			const wrapper = createWrapper( {
				modelValue: newStringValue( 'Initial' )
			} );

			await wrapper.findAll( 'input' )[ 0 ].setValue( 'New Value' );

			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();
			expect( emitted![ 0 ][ 0 ] ).toEqual( newStringValue( 'New Value' ) );
		} );

		it( 'handles multiple input changes correctly', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { multiple: true } ),
				modelValue: newStringValue( 'Text1', 'Text2' )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( 'Updated Text2' );

			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();
			expect( emitted![ 0 ][ 0 ] ).toEqual( newStringValue( 'Text1', 'Updated Text2' ) );
		} );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns updated value after input (single)', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { multiple: false } ),
				modelValue: newStringValue( 'Initial' )
			} );
			await wrapper.find( 'input' ).setValue( 'Updated' );
			expect( ( wrapper.vm as unknown as TextInputExposed ).getCurrentValue() ).toEqual( newStringValue( 'Updated' ) );
		} );

		it( 'returns updated values after input (multiple)', async () => {
			const wrapper = createWrapper( {
				property: newTextProperty( { multiple: true } ),
				modelValue: newStringValue( 'First', 'Second' )
			} );
			await wrapper.findAll( 'input' )[ 1 ].setValue( 'Updated Second' );
			expect( ( wrapper.vm as unknown as TextInputExposed ).getCurrentValue() ).toEqual( newStringValue( 'First', 'Updated Second' ) );
		} );
	} );

} );
