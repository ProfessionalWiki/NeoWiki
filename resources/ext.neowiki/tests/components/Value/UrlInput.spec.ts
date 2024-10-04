import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import UrlInput from '@/components/Value/UrlInput.vue';
import { CdxField } from '@wikimedia/codex';
import { newStringValue } from '@neo/domain/Value';

describe( 'UrlInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	it( 'renders correctly', () => {
		const wrapper = mount( UrlInput, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: newStringValue( 'https://example.com' )
			}
		} );

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'validates required field', async () => {
		const wrapper = mount( UrlInput, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: newStringValue( 'https://example.com' )
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( '' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'validates valid URL', async () => {
		const wrapper = mount( UrlInput, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: newStringValue( 'https://example.com' )
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 'https://valid-url.com' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual( {} );
	} );

	it( 'validates invalid URL', async () => {
		const wrapper = mount( UrlInput, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: newStringValue( 'https://example.com' )
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 'invalid-url' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-invalid-url' );
	} );

	it( 'emits valid field', async () => {
		const wrapper = mount( UrlInput, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: newStringValue( 'https://example.com' )
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 'https://valid-url.com' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ true ] );
	} );

	it( 'emits invalid field', async () => {
		const wrapper = mount( UrlInput, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: newStringValue( 'https://example.com' )
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 'invalid-url' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ false ] );
	} );
} );
