import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxCheckbox, CdxField } from '@wikimedia/codex';
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
			property: newBooleanProperty( { name: 'Is public' } ),
			...props,
		} );
	}

	function getCurrentValue( wrapper: VueWrapper ): unknown {
		return ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue();
	}

	it( 'renders a CdxCheckbox wrapped in a CdxField, inline-labelled with the property name', () => {
		const wrapper = newWrapper();

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.findComponent( CdxCheckbox ).exists() ).toBe( true );
		expect( wrapper.findComponent( CdxCheckbox ).text() ).toContain( 'Is public' );
	} );

	it( 'shows the caller-supplied label as the field heading when it differs from the property name (schema-editor case)', () => {
		const wrapper = newWrapper( { label: 'Initial value' } );

		expect( wrapper.findComponent( CdxField ).props( 'hideLabel' ) ).toBe( false );
		expect( wrapper.text() ).toContain( 'Initial value' );
		expect( wrapper.findComponent( CdxCheckbox ).text() ).toContain( 'Is public' );
	} );

	it( 'hides the field heading when the caller-supplied label equals the property name (subject-editor case)', () => {
		const wrapper = newWrapper( { label: 'Is public' } );

		expect( wrapper.findComponent( CdxField ).props( 'hideLabel' ) ).toBe( true );
		expect( wrapper.findComponent( CdxCheckbox ).text() ).toContain( 'Is public' );
	} );

	it( 'renders the checkbox unchecked when modelValue is BooleanValue(false)', () => {
		const wrapper = newWrapper( { modelValue: newBooleanValue( false ) } );

		expect( wrapper.findComponent( CdxCheckbox ).props( 'modelValue' ) ).toBe( false );
	} );

	it( 'renders the checkbox checked when modelValue is BooleanValue(true)', () => {
		const wrapper = newWrapper( { modelValue: newBooleanValue( true ) } );

		expect( wrapper.findComponent( CdxCheckbox ).props( 'modelValue' ) ).toBe( true );
	} );

	it( 'renders the checkbox unchecked when modelValue is undefined', () => {
		const wrapper = newWrapper( { modelValue: undefined } );

		expect( wrapper.findComponent( CdxCheckbox ).props( 'modelValue' ) ).toBe( false );
	} );

	it( 'falls back to unchecked when modelValue is the wrong value type', () => {
		const wrapper = newWrapper( { modelValue: newStringValue( 'not a boolean' ) } );

		expect( wrapper.findComponent( CdxCheckbox ).props( 'modelValue' ) ).toBe( false );
	} );

	it( 'emits BooleanValue(true) when the checkbox is checked', async () => {
		const wrapper = newWrapper();

		await wrapper.findComponent( CdxCheckbox ).vm.$emit( 'update:modelValue', true );

		expect( wrapper.emitted( 'update:modelValue' )?.[ 0 ] ).toEqual( [ newBooleanValue( true ) ] );
	} );

	it( 'emits BooleanValue(false) when the checkbox is unchecked', async () => {
		const wrapper = newWrapper( { modelValue: newBooleanValue( true ) } );

		await wrapper.findComponent( CdxCheckbox ).vm.$emit( 'update:modelValue', false );

		expect( wrapper.emitted( 'update:modelValue' )?.[ 0 ] ).toEqual( [ newBooleanValue( false ) ] );
	} );

	it( 'reacts to an external modelValue change', async () => {
		const wrapper = newWrapper( { modelValue: newBooleanValue( false ) } );

		await wrapper.setProps( { modelValue: newBooleanValue( true ) } );

		expect( wrapper.findComponent( CdxCheckbox ).props( 'modelValue' ) ).toBe( true );
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

		it( 'returns the new value after the user checks the checkbox', async () => {
			const wrapper = newWrapper( { modelValue: newBooleanValue( false ) } );

			await wrapper.findComponent( CdxCheckbox ).vm.$emit( 'update:modelValue', true );

			expect( getCurrentValue( wrapper ) ).toEqual( newBooleanValue( true ) );
		} );
	} );

	it( 'emits a Value of type Boolean (not String or Number)', async () => {
		const wrapper = newWrapper();

		await wrapper.findComponent( CdxCheckbox ).vm.$emit( 'update:modelValue', true );

		const events = wrapper.emitted( 'update:modelValue' );
		const emitted = events?.[ 0 ]?.[ 0 ] as { type: ValueType } | undefined;
		expect( emitted?.type ).toBe( ValueType.Boolean );
	} );

	describe( 'Server violations', () => {
		function newWrapperWithViolationOnFoo(): VueWrapper {
			return newWrapper( {
				property: newBooleanProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'type-mismatch', args: [ 'string', 'boolean' ], valuePartIndex: null },
				],
			} );
		}

		it( 'shows a field-level server violation as the field error', () => {
			const wrapper = newWrapperWithViolationOnFoo();

			const field = wrapper.findComponent( CdxField );
			expect( field.props( 'status' ) ).toBe( 'error' );
			expect( field.props( 'messages' ) ).toEqual( { error: 'neowiki-field-type-mismatch' } );
		} );

		it( 'emits clear-server-violation when the user edits the field', async () => {
			const wrapper = newWrapperWithViolationOnFoo();

			await wrapper.find( 'input[type="checkbox"]' ).setValue( true );

			expect( wrapper.emitted( 'clear-server-violation' )![ 0 ] ).toEqual( [
				{ propertyName: 'Foo', valuePartIndex: null },
			] );
		} );
	} );
} );
