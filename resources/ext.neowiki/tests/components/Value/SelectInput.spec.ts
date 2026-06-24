import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField } from '@wikimedia/codex';
import SelectInput from '@/components/Value/SelectInput.vue';
import { newSelectProperty, SelectProperty } from '@/domain/propertyTypes/Select';
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
} );
