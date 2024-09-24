import { mount } from '@vue/test-utils';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import NeoNumberField from '@/components/UIComponents/NeoNumberField.vue';
import { CdxField } from '@wikimedia/codex';

describe( 'NeoNumberField', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	it( 'renders correctly', () => {
		const wrapper = mount( NeoNumberField, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: 10
			}
		} );

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'validates required field', async () => {
		const wrapper = mount( NeoNumberField, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: 10
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( '' );
		await input.trigger( 'input' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'validates maxValue for the number', async () => {
		const wrapper = mount( NeoNumberField, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: 10
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 55 );
		await input.trigger( 'input' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-max-value' );
	} );

	it( 'validates minValue for the number', async () => {
		const wrapper = mount( NeoNumberField, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: 10
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 1 );
		await input.trigger( 'input' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-min-value' );
	} );

	it( 'emits valid field', async () => {
		const wrapper = mount( NeoNumberField, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: 10
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 45 );
		await input.trigger( 'input' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ true ] );
	} );

	it( 'emits invalid field', async () => {
		const wrapper = mount( NeoNumberField, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: 10
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 55 );
		await input.trigger( 'input' );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ false ] );
	} );
} );
