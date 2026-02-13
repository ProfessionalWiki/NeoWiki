import { VueWrapper, mount } from '@vue/test-utils';
import { describe, expect, it } from 'vitest';
import NeoMultiLookupInput from '@/components/common/NeoMultiLookupInput.vue';
import { CdxMessage } from '@wikimedia/codex';
import { h } from 'vue';

function createWrapper( props: Partial<InstanceType<typeof NeoMultiLookupInput>['$props']> = {} ): VueWrapper {
	return mount( NeoMultiLookupInput, {
		props: {
			label: 'Test Label',
			modelValue: [ null ],
			...props,
		},
		slots: {
			input: ( slotProps: any ) => h( 'div', {
				class: 'stub-input',
				'data-value': slotProps.value,
				'data-status': slotProps.status,
				'data-aria-label': slotProps.ariaLabel,
			} ),
		},
	} );
}

function getSlotInputs( wrapper: VueWrapper ): any[] {
	return wrapper.findAll( '.stub-input' );
}

describe( 'NeoMultiLookupInput', () => {

	describe( 'rendering', () => {
		it( 'renders one slot per value plus trailing empty slot', () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC', 'sDEF' ] } );
			const inputs = getSlotInputs( wrapper );

			expect( inputs ).toHaveLength( 3 );
			expect( inputs[ 0 ].attributes( 'data-value' ) ).toBe( 'sABC' );
			expect( inputs[ 1 ].attributes( 'data-value' ) ).toBe( 'sDEF' );
			expect( inputs[ 2 ].attributes( 'data-value' ) ).toBeFalsy();
		} );

		it( 'renders only one slot if modelValue is empty', () => {
			const wrapper = createWrapper( { modelValue: [] } );
			expect( getSlotInputs( wrapper ) ).toHaveLength( 1 );
		} );

		it( 'renders only one slot if modelValue is just null', () => {
			const wrapper = createWrapper( { modelValue: [ null ] } );
			expect( getSlotInputs( wrapper ) ).toHaveLength( 1 );
		} );

		it( 'renders two slots if modelValue has one value', () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC' ] } );
			const inputs = getSlotInputs( wrapper );

			expect( inputs ).toHaveLength( 2 );
			expect( inputs[ 0 ].attributes( 'data-value' ) ).toBe( 'sABC' );
			expect( inputs[ 1 ].attributes( 'data-value' ) ).toBeFalsy();
		} );

		it( 'assigns correct aria-label to each slot', () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC', 'sDEF' ], label: 'Relations' } );
			const inputs = getSlotInputs( wrapper );

			expect( inputs[ 0 ].attributes( 'data-aria-label' ) ).toBe( 'Relations item 1' );
			expect( inputs[ 1 ].attributes( 'data-aria-label' ) ).toBe( 'Relations item 2' );
			expect( inputs[ 2 ].attributes( 'data-aria-label' ) ).toBe( 'Relations item 3' );
		} );
	} );

	describe( 'value handling', () => {
		it( 'emits values excluding trailing null on selection change', async () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC' ] } );

			// Simulate the slot calling onUpdate on the trailing null slot (index 1)
			const vm = wrapper.vm as any;
			vm.onUpdate( 1, 'sDEF' );
			await wrapper.vm.$nextTick();

			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();
			expect( emitted![ emitted!.length - 1 ][ 0 ] ).toEqual( [ 'sABC', 'sDEF' ] );
		} );

		it( 'normalizes by removing null entries in the middle', () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC', null, 'sDEF', null, null ] } );
			const inputs = getSlotInputs( wrapper );

			expect( inputs ).toHaveLength( 3 );
			expect( inputs[ 0 ].attributes( 'data-value' ) ).toBe( 'sABC' );
			expect( inputs[ 1 ].attributes( 'data-value' ) ).toBe( 'sDEF' );
			expect( inputs[ 2 ].attributes( 'data-value' ) ).toBeFalsy();
		} );

		it( 'always maintains trailing null slot', async () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC' ] } );
			const vm = wrapper.vm as any;

			// Fill in the trailing slot
			vm.onUpdate( 1, 'sDEF' );
			await wrapper.vm.$nextTick();

			// Should now have 3 slots: sABC, sDEF, null
			expect( getSlotInputs( wrapper ) ).toHaveLength( 3 );
		} );

		it( 'removes slot when value is set to null', async () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC', 'sDEF' ] } );
			expect( getSlotInputs( wrapper ) ).toHaveLength( 3 );

			const vm = wrapper.vm as any;
			vm.onUpdate( 1, null );
			await wrapper.vm.$nextTick();

			expect( getSlotInputs( wrapper ) ).toHaveLength( 2 );
			const inputs = getSlotInputs( wrapper );
			expect( inputs[ 0 ].attributes( 'data-value' ) ).toBe( 'sABC' );
			expect( inputs[ 1 ].attributes( 'data-value' ) ).toBeFalsy();
		} );

		it( 'emits single null array when only value is cleared', async () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC' ] } );
			const vm = wrapper.vm as any;

			vm.onUpdate( 0, null );
			await wrapper.vm.$nextTick();

			const emitted = wrapper.emitted( 'update:modelValue' );
			expect( emitted ).toBeTruthy();
			expect( emitted![ emitted!.length - 1 ][ 0 ] ).toEqual( [ null ] );
		} );

		it( 'preserves focused slot when its value is set to null', async () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC', 'sDEF' ] } );
			expect( getSlotInputs( wrapper ) ).toHaveLength( 3 );

			const vm = wrapper.vm as any;
			vm.onFocus( 1 );
			vm.onUpdate( 1, null );
			await wrapper.vm.$nextTick();

			expect( getSlotInputs( wrapper ) ).toHaveLength( 3 );
			const inputs = getSlotInputs( wrapper );
			expect( inputs[ 0 ].attributes( 'data-value' ) ).toBe( 'sABC' );
			expect( inputs[ 1 ].attributes( 'data-value' ) ).toBeFalsy();
			expect( inputs[ 2 ].attributes( 'data-value' ) ).toBeFalsy();
		} );

		it( 'removes empty slot on blur after value was cleared while focused', async () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC', 'sDEF' ] } );
			const vm = wrapper.vm as any;

			vm.onFocus( 1 );
			vm.onUpdate( 1, null );
			await wrapper.vm.$nextTick();
			expect( getSlotInputs( wrapper ) ).toHaveLength( 3 );

			vm.onBlur( 1 );
			await wrapper.vm.$nextTick();

			expect( getSlotInputs( wrapper ) ).toHaveLength( 2 );
			const inputs = getSlotInputs( wrapper );
			expect( inputs[ 0 ].attributes( 'data-value' ) ).toBe( 'sABC' );
			expect( inputs[ 1 ].attributes( 'data-value' ) ).toBeFalsy();
		} );

		it( 'keeps slot when new value is selected after clearing while focused', async () => {
			const wrapper = createWrapper( { modelValue: [ 'sABC', 'sDEF' ] } );
			const vm = wrapper.vm as any;

			vm.onFocus( 1 );
			vm.onUpdate( 1, null );
			await wrapper.vm.$nextTick();
			expect( getSlotInputs( wrapper ) ).toHaveLength( 3 );

			vm.onUpdate( 1, 'sGHI' );
			await wrapper.vm.$nextTick();

			expect( getSlotInputs( wrapper ) ).toHaveLength( 3 );
			const inputs = getSlotInputs( wrapper );
			expect( inputs[ 0 ].attributes( 'data-value' ) ).toBe( 'sABC' );
			expect( inputs[ 1 ].attributes( 'data-value' ) ).toBe( 'sGHI' );
			expect( inputs[ 2 ].attributes( 'data-value' ) ).toBeFalsy();
		} );

	} );

	describe( 'message display', () => {
		it( 'does not display message initially', () => {
			const wrapper = createWrapper( {
				modelValue: [ 'sABC' ],
				messages: [ { error: 'Error on first' } ],
			} );
			expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( false );
		} );

		it( 'displays message after input is blurred', async () => {
			const wrapper = createWrapper( {
				modelValue: [ 'sABC' ],
				messages: [ { error: 'Error on first' } ],
			} );

			const vm = wrapper.vm as any;
			vm.onBlur( 0 );
			await wrapper.vm.$nextTick();

			const message = wrapper.findComponent( CdxMessage );
			expect( message.exists() ).toBe( true );
			expect( message.props( 'type' ) ).toBe( 'error' );
			expect( message.text() ).toContain( 'Error on first' );
		} );

		it( 'passes correct status to slot based on messages', () => {
			const wrapper = createWrapper( {
				modelValue: [ 'sABC', 'sDEF' ],
				messages: [ {}, { warning: 'Warning on second' } ],
			} );
			const inputs = getSlotInputs( wrapper );

			expect( inputs[ 0 ].attributes( 'data-status' ) ).toBe( 'default' );
			expect( inputs[ 1 ].attributes( 'data-status' ) ).toBe( 'warning' );
		} );

		it( 'does not display message if input has no message entry, even after blur', async () => {
			const wrapper = createWrapper( {
				modelValue: [ 'sABC' ],
				messages: [],
			} );

			const vm = wrapper.vm as any;
			vm.onBlur( 0 );
			await wrapper.vm.$nextTick();

			expect( wrapper.findComponent( CdxMessage ).exists() ).toBe( false );
		} );
	} );
} );
