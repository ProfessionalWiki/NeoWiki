import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { CdxField } from '@wikimedia/codex';
import NeoTextField from '@/components/UIComponents/NeoTextField.vue';

describe( 'NeoTextField', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	it( 'renders correctly', () => {
		const wrapper = mount( NeoTextField, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: 'Test'
			}
		} );

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'validates required field', async () => {
		const wrapper = mount( NeoTextField, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: 'Test'
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( '' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'emits valid field', async () => {
		const wrapper = mount( NeoTextField, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: 'Test'
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 'Valid Text' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ true ] );
	} );

	it( 'emits invalid field', async () => {
		const wrapper = mount( NeoTextField, {
			props: {
				required: true,
				label: 'Test Label',
				modelValue: 'Test'
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( '' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ false ] );
	} );
} );
