import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField, CdxToggleSwitch } from '@wikimedia/codex';
import { newBooleanValue, newStringValue, ValueType } from '@/domain/Value';
import BooleanInput from '@/components/Value/BooleanInput.vue';
import { newBooleanProperty, BooleanProperty } from '@/domain/propertyTypes/Boolean';
import { ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';

describe( 'BooleanInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str,
			} ) ),
		} );
	} );

	function newWrapper( props: Partial<ValueInputProps<BooleanProperty>> = {} ): VueWrapper {
		return createTestWrapper( BooleanInput, {
			modelValue: undefined,
			label: 'Test Label',
			property: newBooleanProperty( {} ),
			...props,
		} );
	}

	function getCurrentValue( wrapper: VueWrapper ): unknown {
		return ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue();
	}

	it( 'renders a CdxToggleSwitch wrapped in a CdxField', () => {
		const wrapper = newWrapper();

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.findComponent( CdxToggleSwitch ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'renders the toggle in the off position when modelValue is BooleanValue(false)', () => {
		const wrapper = newWrapper( { modelValue: newBooleanValue( false ) } );

		expect( wrapper.findComponent( CdxToggleSwitch ).props( 'modelValue' ) ).toBe( false );
	} );

	it( 'renders the toggle in the on position when modelValue is BooleanValue(true)', () => {
		const wrapper = newWrapper( { modelValue: newBooleanValue( true ) } );

		expect( wrapper.findComponent( CdxToggleSwitch ).props( 'modelValue' ) ).toBe( true );
	} );

	it( 'renders the toggle in the off position when modelValue is undefined', () => {
		const wrapper = newWrapper( { modelValue: undefined } );

		expect( wrapper.findComponent( CdxToggleSwitch ).props( 'modelValue' ) ).toBe( false );
	} );

	it( 'falls back to the off position when modelValue is the wrong value type', () => {
		const wrapper = newWrapper( { modelValue: newStringValue( 'not a boolean' ) } );

		expect( wrapper.findComponent( CdxToggleSwitch ).props( 'modelValue' ) ).toBe( false );
	} );

	it( 'emits BooleanValue(true) when the toggle is switched on', async () => {
		const wrapper = newWrapper();

		await wrapper.findComponent( CdxToggleSwitch ).vm.$emit( 'update:modelValue', true );

		expect( wrapper.emitted( 'update:modelValue' )?.[ 0 ] ).toEqual( [ newBooleanValue( true ) ] );
	} );

	it( 'emits BooleanValue(false) when the toggle is switched off', async () => {
		const wrapper = newWrapper( { modelValue: newBooleanValue( true ) } );

		await wrapper.findComponent( CdxToggleSwitch ).vm.$emit( 'update:modelValue', false );

		expect( wrapper.emitted( 'update:modelValue' )?.[ 0 ] ).toEqual( [ newBooleanValue( false ) ] );
	} );

	it( 'reacts to an external modelValue change', async () => {
		const wrapper = newWrapper( { modelValue: newBooleanValue( false ) } );

		await wrapper.setProps( { modelValue: newBooleanValue( true ) } );

		expect( wrapper.findComponent( CdxToggleSwitch ).props( 'modelValue' ) ).toBe( true );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns the initial BooleanValue(true) when modelValue is true', () => {
			const wrapper = newWrapper( { modelValue: newBooleanValue( true ) } );

			expect( getCurrentValue( wrapper ) ).toEqual( newBooleanValue( true ) );
		} );

		it( 'returns the initial BooleanValue(false) when modelValue is false', () => {
			const wrapper = newWrapper( { modelValue: newBooleanValue( false ) } );

			expect( getCurrentValue( wrapper ) ).toEqual( newBooleanValue( false ) );
		} );

		it( 'returns BooleanValue(false) for an untouched input with undefined modelValue', () => {
			// Documents the "Boolean is never unset" design choice from PR #837:
			// the input always emits a defined BooleanValue, defaulting to false
			// when there is no model value yet.
			const wrapper = newWrapper( { modelValue: undefined } );

			expect( getCurrentValue( wrapper ) ).toEqual( newBooleanValue( false ) );
		} );

		it( 'returns the toggled value after the user switches the toggle on', async () => {
			const wrapper = newWrapper( { modelValue: newBooleanValue( false ) } );

			await wrapper.findComponent( CdxToggleSwitch ).vm.$emit( 'update:modelValue', true );

			expect( getCurrentValue( wrapper ) ).toEqual( newBooleanValue( true ) );
		} );
	} );

	it( 'emits a Value of type Boolean (not String or Number)', async () => {
		const wrapper = newWrapper();

		await wrapper.findComponent( CdxToggleSwitch ).vm.$emit( 'update:modelValue', true );

		const events = wrapper.emitted( 'update:modelValue' );
		const emitted = events?.[ 0 ]?.[ 0 ] as { type: ValueType } | undefined;
		expect( emitted?.type ).toBe( ValueType.Boolean );
	} );
} );
