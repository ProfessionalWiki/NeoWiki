import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import UrlInput from '@/components/Value/UrlInput.vue';
import { CdxField } from '@wikimedia/codex';
import { newStringValue, StringValue } from '@neo/domain/Value';
import { newUrlProperty } from '@neo/domain/valueFormats/Url.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';

describe( 'UrlInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	const newStringValueWithUrls = (): StringValue => newStringValue( 'https://example.com', 'https://example2.com' );

	function createWrapper( props: Partial<InstanceType<typeof UrlInput>['$props']> = {} ): VueWrapper<InstanceType<typeof UrlInput>> {
		return createTestWrapper( UrlInput, {
			modelValue: newStringValueWithUrls(),
			property: newUrlProperty( { multiple: true } ),
			...props
		} );
	}

	it( 'renders correctly with single URL', () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example.com' )
		} );

		expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 1 );
		expect( wrapper.findAll( 'input' ) ).toHaveLength( 1 );
	} );

	it( 'renders correctly with multiple URLs', () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example1.com', 'https://example2.com' )
		} );

		expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 2 );
		expect( wrapper.findAll( 'input' ) ).toHaveLength( 2 );
	} );

	it( 'removes URL field when delete button is clicked', async () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example1.com', 'https://example2.com' )
		} );

		await wrapper.findAll( '.delete-button' )[ 0 ].trigger( 'click' );

		expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 1 );
		expect( wrapper.findAll( 'input' ) ).toHaveLength( 1 );
	} );

	describe( 'validation', () => {

		const assertFieldIsValid = ( field: any ): void => {
			expect( field.props( 'status' ) ).toBe( 'success' );
			expect( field.props( 'messages' ) ).toEqual( {} );
		};

		const assertFieldIsInvalid = ( field: any ): void => {
			expect( field.props( 'status' ) ).toBe( 'error' );
			expect( field.props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-invalid-url' );
		};

		it( 'succeeds for multiple valid URLs', async () => {
			const wrapper = createWrapper();

			await wrapper.findAll( 'input' )[ 1 ].setValue( 'https://valid-url.com' );

			const fields = wrapper.findAllComponents( CdxField );
			assertFieldIsValid( fields[ 0 ] );
			assertFieldIsValid( fields[ 1 ] );
		} );

		it( 'fails for all invalid URLs', async () => {
			const wrapper = createWrapper( {
				modelValue: newStringValue( 'https://valid1.com', 'https://valid2.com', 'https://valid3.com' )
			} );

			await wrapper.findAll( 'input' )[ 0 ].setValue( 'invalid-url1' );
			await wrapper.findAll( 'input' )[ 2 ].setValue( 'invalid-url3' );

			const fields = wrapper.findAllComponents( CdxField );
			assertFieldIsInvalid( fields[ 0 ] );
			assertFieldIsValid( fields[ 1 ] );
			assertFieldIsInvalid( fields[ 2 ] );
		} );

		it( 'succeeds for single empty value part when the value is optional', async () => {
			const wrapper = createWrapper( {
				property: newUrlProperty( { required: false } )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( '' );

			const fields = wrapper.findAllComponents( CdxField );
			assertFieldIsValid( fields[ 0 ] );
		} );

		it( 'succeeds for empty value parts when the value is required but there are valid non-empty parts', async () => {
			const wrapper = createWrapper( {
				property: newUrlProperty( { required: true, multiple: true } ),
				modelValue: newStringValue( 'https://valid1.com', 'https://valid2.com', 'https://valid3.com' )
			} );

			await wrapper.findAll( 'input' )[ 0 ].setValue( 'https://valid4.com' );
			await wrapper.findAll( 'input' )[ 2 ].setValue( '' );

			const fields = wrapper.findAllComponents( CdxField );
			assertFieldIsValid( fields[ 0 ] );
			assertFieldIsValid( fields[ 1 ] );
			assertFieldIsValid( fields[ 2 ] );
		} );

		it( 'emits validation for multiple fields', async () => {
			const wrapper = createWrapper();

			await wrapper.vm.onInput( 'https://valid-url.com', 1 );

			expect( wrapper.vm.validationState.messages[ 1 ] ).toEqual( {} );
			expect( wrapper.vm.validationState.statuses[ 1 ] ).toEqual( 'success' );

			await wrapper.vm.onInput( 'invalid-url', 1 );
			expect( wrapper.vm.validationState.messages[ 1 ].error ).toEqual( 'neowiki-field-invalid-url' );
			expect( wrapper.vm.validationState.statuses[ 1 ] ).toEqual( 'error' );
		} );

	} );

	describe( 'add button', () => {

		it( 'is enabled when all URLs are valid', async () => {
			const wrapper = createWrapper();

			const addButton = wrapper.find( 'button.add-url-button' );
			expect( addButton.attributes( 'disabled' ) ).toBeUndefined();
		} );

		it( 'is disabled when one URL fields is invalid', async () => {
			const wrapper = createWrapper( {
				modelValue: newStringValue( 'https://valid1.com', 'https://valid2.com', 'https://valid3.com' )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( 'invalid' );

			const addButton = wrapper.find( 'button.add-url-button' );
			expect( addButton.attributes( 'disabled' ) ).toBeDefined();
		} );

		it( 'adds new URL field when clicked', async () => {
			const wrapper = createWrapper();

			expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 2 );
			expect( wrapper.findAll( 'input' ) ).toHaveLength( 2 );

			const addButton = wrapper.find( 'button.add-url-button' );
			expect( addButton.exists() ).toBe( true );

			await addButton.trigger( 'click' );

			expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 3 );
			expect( wrapper.findAll( 'input' ) ).toHaveLength( 3 );

			const emittedValues = wrapper.emitted( 'update:modelValue' );
			expect( emittedValues ).toBeTruthy();
			expect( emittedValues![ 0 ][ 0 ] ).toEqual( newStringValue( 'https://example.com', 'https://example2.com' ) );
		} );

	} );

} );
