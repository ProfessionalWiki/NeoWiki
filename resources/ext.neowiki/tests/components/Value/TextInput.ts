import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import TextInput from '@/components/Value/TextInput.vue';
import { CdxField } from '@wikimedia/codex';
import { newStringValue } from '@neo/domain/Value';

describe( 'TextInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	it( 'renders correctly', () => {
		const wrapper = mount( TextInput, {
			props: {
				required: true,
				minLength: 2,
				maxLength: 50,
				label: 'Test Label',
				modelValue: newStringValue( 'Test' )
			}
		} );

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'validates required field', async () => {
		const wrapper = mount( TextInput, {
			props: {
				required: true,
				minLength: 2,
				maxLength: 50,
				label: 'Test Label',
				modelValue: newStringValue( 'Test' )
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( '' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'validates maxLength for the text', async () => {
		const wrapper = mount( TextInput, {
			props: {
				required: true,
				minLength: 2,
				maxLength: 10,
				label: 'Test Label',
				modelValue: newStringValue( 'Test' )
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 'This is a very long text' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-max-length' );
	} );

	it( 'validates minLength for the text', async () => {
		const wrapper = mount( TextInput, {
			props: {
				required: true,
				minLength: 5,
				maxLength: 50,
				label: 'Test Label',
				modelValue: newStringValue( 'Test' )
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 'A' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-min-length' );
	} );

	it( 'emits valid field', async () => {
		const wrapper = mount( TextInput, {
			props: {
				required: true,
				minLength: 2,
				maxLength: 50,
				label: 'Test Label',
				modelValue: newStringValue( 'Test' )
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 'Valid Text' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ true ] );
	} );

	it( 'emits invalid field', async () => {
		const wrapper = mount( TextInput, {
			props: {
				required: true,
				minLength: 2,
				maxLength: 50,
				label: 'Test Label',
				modelValue: newStringValue( 'Test' )
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( '' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ false ] );
	} );
} );
