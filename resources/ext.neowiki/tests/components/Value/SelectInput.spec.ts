import { flushPromises, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField, CdxMultiselectLookup, CdxSelect } from '@wikimedia/codex';
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

	describe( 'clearing server violations', () => {
		it( 'clears a part-indexed violation using its own index', async () => {
			const wrapper = newWrapper( {
				serverViolations: [
					{ propertyName: 'Status', code: 'invalid-option', args: [ 'bogus' ], valuePartIndex: 0 },
				],
			} );

			wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'open' );
			await wrapper.vm.$nextTick();

			// SelectType emits invalid-option per part, and the parent drops violations by exact
			// valuePartIndex match, so a null here would never clear an indexed one.
			expect( wrapper.emitted( 'clear-server-violation' ) ).toEqual( [
				[ { propertyName: 'Status', valuePartIndex: 0 } ],
			] );
		} );

		it( 'does not emit when the property has no violation', async () => {
			const wrapper = newWrapper( {
				serverViolations: [
					{ propertyName: 'Other', code: 'required', args: [], valuePartIndex: null },
				],
			} );

			wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'open' );
			await wrapper.vm.$nextTick();

			expect( wrapper.emitted( 'clear-server-violation' ) ).toBeUndefined();
		} );

		it( 'clears a field-level (null-index) violation on the property', async () => {
			const wrapper = newWrapper( {
				serverViolations: [
					{ propertyName: 'Status', code: 'required', args: [], valuePartIndex: null },
				],
			} );

			wrapper.findComponent( CdxSelect ).vm.$emit( 'update:selected', 'open' );
			await wrapper.vm.$nextTick();

			// A required violation carries valuePartIndex: null; the parent drops by exact match,
			// so the clear must carry null too or the stale error outlives the user's fix.
			expect( wrapper.emitted( 'clear-server-violation' ) ).toEqual( [
				[ { propertyName: 'Status', valuePartIndex: null } ],
			] );
		} );

		it( 'clears every violation on a multi-select via the chips path, each by its own index', async () => {
			const wrapper = newWrapper( {
				property: newSelectProperty( {
					name: 'Status',
					multiple: true,
					options: [
						{ id: 'open', label: 'Open' },
						{ id: 'closed', label: 'Closed' },
						{ id: 'archived', label: 'Archived' },
					],
				} ),
				serverViolations: [
					{ propertyName: 'Status', code: 'invalid-option', args: [ 'a' ], valuePartIndex: 0 },
					{ propertyName: 'Status', code: 'invalid-option', args: [ 'b' ], valuePartIndex: 2 },
				],
			} );

			wrapper.findComponent( CdxMultiselectLookup ).vm.$emit(
				'update:input-chips', [ { value: 'open', label: 'Open' } ],
			);
			await flushPromises();

			// The multi path clears through the chips watcher (nextTick + emitPending guard).
			// Gapped indices 0 and 2: a positional counter would emit 0 and 1, stranding index 2.
			// v-model:input-chips is two-way and may echo, so assert presence rather than exact order.
			const cleared = wrapper.emitted( 'clear-server-violation' );
			expect( cleared ).toContainEqual( [ { propertyName: 'Status', valuePartIndex: 0 } ] );
			expect( cleared ).toContainEqual( [ { propertyName: 'Status', valuePartIndex: 2 } ] );
		} );
	} );
} );
