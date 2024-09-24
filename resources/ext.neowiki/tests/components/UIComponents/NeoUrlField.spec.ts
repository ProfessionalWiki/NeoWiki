import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import NeoUrlField from '@/components/UIComponents/NeoUrlField.vue';
import { CdxField } from '@wikimedia/codex';

describe( 'NeoUrlField', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	it( 'renders correctly', () => {
		const wrapper = mount( NeoUrlField, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: 'https://example.com'
			}
		} );

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'validates required field', async () => {
		const wrapper = mount( NeoUrlField, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: 'https://example.com'
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( '' );
		await input.trigger( 'input' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'validates valid URL', async () => {
		const wrapper = mount( NeoUrlField, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: 'https://example.com'
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 'https://valid-url.com' );
		await input.trigger( 'input' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual( {} );
	} );

	it( 'validates invalid URL', async () => {
		const wrapper = mount( NeoUrlField, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: 'https://example.com'
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 'invalid-url' );
		await input.trigger( 'input' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-invalid-url' );
	} );

	it( 'emits valid field', async () => {
		const wrapper = mount( NeoUrlField, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: 'https://example.com'
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 'https://valid-url.com' );
		await input.trigger( 'input' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ true ] );
	} );

	it( 'emits invalid field', async () => {
		const wrapper = mount( NeoUrlField, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: 'https://example.com'
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 'invalid-url' );
		await input.trigger( 'input' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ false ] );
	} );
} );
