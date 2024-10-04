import { mount } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField } from '@wikimedia/codex';
import { newNumberValue } from '@neo/domain/Value';
import NumberInput from '@/components/Value/NumberInput.vue';

describe( 'NumberInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	it( 'renders correctly', () => {
		const wrapper = mount( NumberInput, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: newNumberValue( 10 )
			}
		} );

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'validates required field', async () => {
		const wrapper = mount( NumberInput, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: newNumberValue( 10 )
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( '' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'validates maxValue for the number', async () => {
		const wrapper = mount( NumberInput, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: newNumberValue( 10 )
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 55 );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-max-value' );
	} );

	it( 'validates minValue for the number', async () => {
		const wrapper = mount( NumberInput, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: newNumberValue( 10 )
			}
		} );

		const input = wrapper.find( 'input' );
		await input.setValue( 1 );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-min-value' );
	} );

	it( 'emits valid field', async () => {
		const wrapper = mount( NumberInput, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: newNumberValue( 10 )
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 45 );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ true ] );
	} );

	it( 'emits invalid field', async () => {
		const wrapper = mount( NumberInput, {
			props: {
				required: true,
				minValue: 2,
				maxValue: 50,
				label: 'Test Label',
				modelValue: newNumberValue( 10 )
			}
		} );

		const input = wrapper.find( 'input' );

		await input.setValue( 55 );
		expect( wrapper.emitted( 'validation' )![ 1 ] ).toEqual( [ false ] );
	} );
} );
