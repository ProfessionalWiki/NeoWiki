import { mount, VueWrapper } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import UrlInput from '@/components/Value/UrlInput.vue';
import { CdxField } from '@wikimedia/codex';
import { newStringValue } from '@neo/domain/Value';
import { newUrlProperty } from '@neo/domain/valueFormats/Url.ts';

describe( 'UrlInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	const createWrapper = ( propsData: Partial<InstanceType<typeof UrlInput>['$props']> = {} ): VueWrapper<InstanceType<typeof UrlInput>> => mount( UrlInput, {
		props: {
			modelValue: newStringValue( 'https://example.com' ),
			property: newUrlProperty(),
			...propsData
		}
	} );

	it( 'renders correctly with single URL', () => {
		const wrapper = createWrapper();

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

	it( 'adds new URL field when add button is clicked', async () => {
		const wrapper = createWrapper();

		expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 1 );
		expect( wrapper.findAll( 'input' ) ).toHaveLength( 1 );

		const addButton = wrapper.find( 'button.add-url-button' );
		expect( addButton.exists() ).toBe( true );

		await addButton.trigger( 'click' );

		expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 2 );
		expect( wrapper.findAll( 'input' ) ).toHaveLength( 2 );

		const emittedValues = wrapper.emitted( 'update:modelValue' );
		expect( emittedValues ).toBeTruthy();
		expect( emittedValues![ 0 ][ 0 ] ).toEqual( newStringValue( 'https://example.com', '' ) );
	} );

	it( 'removes URL field when delete button is clicked', async () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example1.com', 'https://example2.com' )
		} );

		await wrapper.findAll( '.delete-button' )[ 0 ].trigger( 'click' );

		expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 1 );
		expect( wrapper.findAll( 'input' ) ).toHaveLength( 1 );
	} );

	it( 'validates required field when multiple URLs', async () => {
		const wrapper = createWrapper( {
			property: newUrlProperty( { required: true } ),
			modelValue: newStringValue( 'https://example1.com', '' )
		} );

		await wrapper.findAll( 'input' )[ 0 ].setValue( '' );

		const fields = wrapper.findAllComponents( CdxField );
		expect( fields[ 0 ].props( 'status' ) ).toBe( 'success' );
		expect( fields[ 0 ].props( 'messages' ) ).toEqual( {} );
	} );

	it( 'validates valid URLs in multiple fields', async () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example1.com', '' )
		} );

		await wrapper.findAll( 'input' )[ 1 ].setValue( 'https://valid-url.com' );

		const fields = wrapper.findAllComponents( CdxField );
		expect( fields[ 0 ].props( 'status' ) ).toBe( 'success' );
		expect( fields[ 1 ].props( 'status' ) ).toBe( 'success' );
		expect( fields[ 0 ].props( 'messages' ) ).toEqual( {} );
		expect( fields[ 1 ].props( 'messages' ) ).toEqual( {} );
	} );

	it( 'validates invalid URLs in multiple fields', async () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example1.com', '' )
		} );

		await wrapper.findAll( 'input' )[ 1 ].setValue( 'invalid-url' );

		const fields = wrapper.findAllComponents( CdxField );
		expect( fields[ 0 ].props( 'status' ) ).toBe( 'success' );
		expect( fields[ 1 ].props( 'status' ) ).toBe( 'error' );
		expect( fields[ 1 ].props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-invalid-url' );
	} );

	it( 'emits validation for multiple fields', async () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example1.com', '' )
		} );

		await wrapper.vm.onInput( 'https://valid-url.com', 1 );
		expect( wrapper.vm.validationState.messages[ 1 ] ).toEqual( {} );
		expect( wrapper.vm.validationState.statuses[ 1 ] ).toEqual( 'success' );

		await wrapper.vm.onInput( 'invalid-url', 1 );
		expect( wrapper.vm.validationState.messages[ 1 ].error ).toEqual( 'neowiki-field-invalid-url' );
		expect( wrapper.vm.validationState.statuses[ 1 ] ).toEqual( 'error' );
	} );

	it( 'validates optional single field correctly', async () => {
		const wrapper = createWrapper( {
			property: newUrlProperty( { required: false } ),
			modelValue: newStringValue( '' )
		} );

		const fields = wrapper.findAllComponents( CdxField );
		expect( fields[ 0 ].props( 'status' ) ).toBe( 'default' );
		expect( fields[ 0 ].props( 'messages' ) ).toEqual( {} );

		await wrapper.findAll( 'input' )[ 0 ].setValue( 'invalid-url' );
		expect( fields[ 0 ].props( 'status' ) ).toBe( 'error' );
		expect( fields[ 0 ].props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-invalid-url' );
	} );

	it( 'add Button is disabled when URL fields are valid', async () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example.com', 'https://validurl' )
		} );

		const addButton = wrapper.find( 'button.add-url-button' );
		await wrapper.findAll( 'input' )[ 1 ].setValue( 'invalid-url.com' );
		expect( addButton.attributes( 'disabled' ) ).toBeDefined();

	} );

	it( 'add Button is enabled when URL fields are valid', async () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example.com', 'invalid-url' )
		} );

		const addButton = wrapper.find( 'button.add-url-button' );
		await wrapper.findAll( 'input' )[ 1 ].setValue( 'https://valid-url.com' );
		expect( addButton.attributes( 'disabled' ) ).toBeUndefined();

	} );
} );
