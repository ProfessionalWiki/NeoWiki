import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import UrlInput from '@/components/Value/UrlInput.vue';
import NeoMultiTextInput from '@/components/common/NeoMultiTextInput.vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { newStringValue, StringValue } from '@neo/domain/Value';
import { newUrlProperty } from '@neo/domain/propertyTypes/Url.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';
import { ValueInputExposes } from '@/components/Value/ValueInputContract.ts';

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
		expect( wrapper.findAllComponents( NeoMultiTextInput ) ).toHaveLength( 1 );
		expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 2 );

		const inputs = wrapper.findAll( 'input' );
		expect( inputs[ 0 ].element.value ).toBe( 'https://example.com' );
		expect( inputs[ 1 ].element.value ).toBe( '' );
	} );

	it( 'renders correctly with multiple URLs', () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example1.com', 'https://example2.com' )
		} );

		expect( wrapper.findAllComponents( CdxField ) ).toHaveLength( 1 );
		expect( wrapper.findAllComponents( NeoMultiTextInput ) ).toHaveLength( 1 );
		expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 3 );

		const inputs = wrapper.findAll( 'input' );
		expect( inputs[ 0 ].element.value ).toBe( 'https://example1.com' );
		expect( inputs[ 1 ].element.value ).toBe( 'https://example2.com' );
		expect( inputs[ 2 ].element.value ).toBe( '' );
	} );

	it( 'removes URL field when delete button is clicked', async () => {
		const wrapper = createWrapper( {
			modelValue: newStringValue( 'https://example1.com', 'https://example2.com' )
		} );

		await wrapper.findAll( 'input' )[ 1 ].setValue( '' );

		expect( wrapper.findAllComponents( CdxTextInput ) ).toHaveLength( 2 );
	} );

	describe( 'validation', () => {

		const assertFieldIsValid = ( field: any ): void => {
			expect( field.props( 'status' ) ).toBe( 'default' );
			expect( field.props( 'messages' ) ).toEqual( {} );
		};

		const assertMultiInputIsValid = ( neoMultiTextInput: any, index: number ): void => {
			expect( neoMultiTextInput.findAllComponents( CdxTextInput )[ index ].props( 'status' ) ).toBe( 'default' );
			expect( neoMultiTextInput.props( 'messages' )[ index ] ).toEqual( {} );
		};

		const assertMultiInputIsInvalid = ( neoMultiTextInput: any, index: number ): void => {
			expect( neoMultiTextInput.findAllComponents( CdxTextInput )[ index ].props( 'status' ) ).toBe( 'error' );
			expect( neoMultiTextInput.props( 'messages' )[ index ] ).toEqual( { error: 'neowiki-field-invalid-url' } );
		};

		it( 'succeeds for multiple valid URLs', async () => {
			const wrapper = createWrapper();

			await wrapper.findAll( 'input' )[ 1 ].setValue( 'https://valid-url.com' );

			const neoMultiTextInput = wrapper.findAllComponents( NeoMultiTextInput )[ 0 ];

			assertMultiInputIsValid( neoMultiTextInput, 0 );
			assertMultiInputIsValid( neoMultiTextInput, 1 );
		} );

		it( 'fails for all invalid URLs', async () => {
			const wrapper = createWrapper( {
				modelValue: newStringValue( 'https://valid1.com', 'https://valid2.com', 'https://valid3.com' )
			} );

			await wrapper.findAll( 'input' )[ 0 ].setValue( 'invalid-url1' );
			await wrapper.findAll( 'input' )[ 2 ].setValue( 'invalid-url3' );

			const neoMultiTextInput = wrapper.findAllComponents( NeoMultiTextInput )[ 0 ];

			assertMultiInputIsInvalid( neoMultiTextInput, 0 );
			assertMultiInputIsValid( neoMultiTextInput, 1 );
			assertMultiInputIsInvalid( neoMultiTextInput, 2 );
		} );

		it( 'succeeds for single empty value part when the value is optional', async () => {
			const wrapper = createWrapper( {
				property: newUrlProperty( { required: false } )
			} );

			await wrapper.findAll( 'input' )[ 0 ].setValue( '' );

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

			const neoMultiTextInput = wrapper.findAllComponents( NeoMultiTextInput )[ 0 ];
			assertMultiInputIsValid( neoMultiTextInput, 0 );
			assertMultiInputIsValid( neoMultiTextInput, 1 );
		} );

		it( 'shows error on duplicate URLs when uniqueness is required', async () => {
			const wrapper = createWrapper( {
				property: newUrlProperty( { multiple: true, uniqueItems: true } ),
				modelValue: newStringValue( 'https://valid1.com', 'https://valid2.com' )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( 'https://valid1.com' );

			const neoMultiTextInput = wrapper.findAllComponents( NeoMultiTextInput )[ 0 ];

			expect( neoMultiTextInput.findAllComponents( CdxTextInput )[ 1 ].props( 'status' ) ).toBe( 'error' );
			expect( neoMultiTextInput.props( 'messages' )?.[ 1 ] ).toHaveProperty( 'error', 'neowiki-field-unique' );
		} );

	} );

	describe( 'Event handling', () => {
		it( 'emits update:modelValue event when input changes', async () => {
			const wrapper = createWrapper( {
				modelValue: newStringValue( 'http://one.com' )
			} );

			await wrapper.findAll( 'input' )[ 0 ].setValue( 'http://two.com' );

			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();
			expect( emitted![ 1 ][ 0 ] ).toEqual( newStringValue( 'http://two.com' ) );
		} );

		it( 'handles multiple input changes correctly', async () => {
			const wrapper = createWrapper( {
				property: newUrlProperty( { multiple: true } ),
				modelValue: newStringValue( 'http://one.com', 'http://two.com' )
			} );

			await wrapper.findAll( 'input' )[ 1 ].setValue( 'http://three.com' );

			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();
			expect( emitted![ 1 ][ 0 ] ).toEqual( newStringValue( 'http://one.com', 'http://three.com' ) );
		} );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns updated value after input (single)', async () => {
			const wrapper = createWrapper( {
				property: newUrlProperty( { multiple: false } ),
				modelValue: newStringValue( 'https://initial.com' )
			} );
			await wrapper.find( 'input' ).setValue( 'https://updated.net' );
			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toEqual( newStringValue( 'https://updated.net' ) );
		} );

		it( 'returns updated values after input (multiple)', async () => {
			const wrapper = createWrapper( {
				property: newUrlProperty( { multiple: true } ),
				modelValue: newStringValue( 'https://first.com', 'https://second.org' )
			} );
			await wrapper.findAll( 'input' )[ 1 ].setValue( 'https://updated-second.io' );
			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toEqual( newStringValue( 'https://first.com', 'https://updated-second.io' ) );
		} );

		it( 'returns undefined for empty input', () => {
			const wrapper = createWrapper( {
				modelValue: newStringValue( '' )
			} );
			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toBeUndefined();
		} );
	} );
} );
