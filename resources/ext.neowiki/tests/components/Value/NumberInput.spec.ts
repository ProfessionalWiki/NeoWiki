import { mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField } from '@wikimedia/codex';
import { newNumberValue } from '@neo/domain/Value';
import NumberInput from '@/components/Value/NumberInput.vue';
import { newNumberProperty, NumberProperty } from '@neo/domain/valueFormats/Number';
import { ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

describe( 'NumberInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str
			} ) )
		} );
	} );

	function newWrapper( props: Partial<ValueInputProps<NumberProperty>> = {} ): VueWrapper {
		return mount( NumberInput, {
			props: {
				modelValue: newNumberValue( 10 ),
				label: 'Test Label',
				property: newNumberProperty( {} ),
				...props
			},
			global: {
				provide: NeoWikiServices.getServices()
			}
		} );
	}

	it( 'renders correctly', () => {
		const wrapper = newWrapper();

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual( {} );
		expect( wrapper.find( 'input' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'validates maxValue for the number', async () => {
		const wrapper = newWrapper( {
			property: newNumberProperty( { minimum: 42, maximum: 50 } )
		} );

		await wrapper.find( 'input' ).setValue( 51 );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-max-value' );
	} );
} );
