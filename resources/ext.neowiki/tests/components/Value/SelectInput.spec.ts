import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField, CdxMultiselectLookup, CdxSelect } from '@wikimedia/codex';
import SelectInput from '@/components/Value/SelectInput.vue';
import { newSelectProperty, SelectProperty } from '@/domain/propertyTypes/Select';
import { PropertyName } from '@/domain/PropertyDefinition';
import { ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';

describe( 'SelectInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str,
			} ) ),
		} );
	} );

	function newWrapper( props: Partial<ValueInputProps<SelectProperty>> = {} ): VueWrapper {
		return createTestWrapper( SelectInput, {
			modelValue: undefined,
			label: 'Status',
			property: newSelectProperty( {
				name: 'Status',
				required: true,
				options: [ { id: 'open', label: 'Open' }, { id: 'closed', label: 'Closed' } ],
			} ),
			...props,
		} );
	}

	it( 'does not flag an empty required select before the user has chosen', () => {
		const wrapper = newWrapper();

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual( {} );
	} );

	it( 'still surfaces a server-sourced violation on the select', () => {
		const wrapper = newWrapper( {
			serverViolations: [
				{ propertyName: 'Status', code: 'required', args: [], valuePartIndex: null },
			],
		} );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	describe( 'with uninitialized options (property just switched to Select)', () => {
		// A bare PropertyDefinition whose Select-specific fields are not yet set, as
		// produced by switching the type dropdown to Select before saving the schema.
		function propertyWithoutOptions( overrides: Partial<SelectProperty> = {} ): SelectProperty {
			return {
				name: new PropertyName( 'Status' ),
				type: 'select',
				description: '',
				required: false,
				default: undefined,
				...overrides,
			} as unknown as SelectProperty;
		}

		it( 'renders the single select without options', () => {
			const wrapper = newWrapper( { property: propertyWithoutOptions() } );

			expect( wrapper.findComponent( CdxSelect ).props( 'menuItems' ) ).toEqual( [] );
		} );

		it( 'filters the multi-select options without crashing', async () => {
			const wrapper = newWrapper( { property: propertyWithoutOptions( { multiple: true } ) } );

			await wrapper.findComponent( CdxMultiselectLookup ).vm.$emit( 'input' );

			expect( wrapper.findComponent( CdxMultiselectLookup ).props( 'menuItems' ) ).toEqual( [] );
		} );
	} );
} );
