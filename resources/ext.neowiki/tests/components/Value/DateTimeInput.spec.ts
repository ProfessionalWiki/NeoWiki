import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField } from '@wikimedia/codex';
import { newStringValue } from '@/domain/Value';
import DateTimeInput from '@/components/Value/DateTimeInput.vue';
import { newDateTimeProperty, DateTimeProperty } from '@/domain/propertyTypes/DateTime';
import { ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';

describe( 'DateTimeInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str,
			} ) ),
		} );
	} );

	function newWrapper( props: Partial<ValueInputProps<DateTimeProperty>> = {} ): VueWrapper {
		return createTestWrapper( DateTimeInput, {
			modelValue: undefined,
			label: 'Test Label',
			property: newDateTimeProperty( {} ),
			...props,
		} );
	}

	it( 'renders correctly', () => {
		const wrapper = newWrapper();

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual( {} );
		expect( wrapper.find( 'input[type="datetime-local"]' ).exists() ).toBe( true );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	// FIXME(#728): pins the current Z-stripping behavior; revisit when the timezone story is decided.
	it( 'displays ISO modelValue as datetime-local input value', () => {
		const wrapper = newWrapper( {
			modelValue: newStringValue( '2025-06-15T14:00:00Z' ),
		} );

		expect( wrapper.find( 'input' ).element.value ).toBe( '2025-06-15T14:00' );
	} );

	it( 'displays empty input when modelValue is undefined', () => {
		const wrapper = newWrapper( { modelValue: undefined } );

		expect( wrapper.find( 'input' ).element.value ).toBe( '' );
	} );

	// FIXME(#728): pins the current Z-stripping behavior on bounds; revisit with the timezone fix.
	it( 'passes minimum and maximum to the input as min/max attributes', () => {
		const wrapper = newWrapper( {
			property: newDateTimeProperty( {
				minimum: '2020-01-01T00:00:00Z',
				maximum: '2030-12-31T23:59:59Z',
			} ),
		} );

		expect( wrapper.find( 'input' ).attributes( 'min' ) ).toBe( '2020-01-01T00:00' );
		expect( wrapper.find( 'input' ).attributes( 'max' ) ).toBe( '2030-12-31T23:59' );
	} );

	it( 'shows required error when required property has empty value', () => {
		const wrapper = newWrapper( {
			modelValue: undefined,
			property: newDateTimeProperty( { required: true } ),
		} );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'shows min-value error when input is before minimum', async () => {
		const wrapper = newWrapper( {
			property: newDateTimeProperty( { minimum: '2025-01-01T00:00:00Z' } ),
		} );

		await wrapper.find( 'input' ).setValue( '2024-12-31T23:59' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-min-value' );
	} );

	it( 'shows max-value error when input is after maximum', async () => {
		const wrapper = newWrapper( {
			property: newDateTimeProperty( { maximum: '2025-12-31T23:59:59Z' } ),
		} );

		await wrapper.find( 'input' ).setValue( '2026-01-01T00:00' );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-max-value' );
	} );

	// FIXME(#728): pins the TZ-naive Z-suffix emission; revisit with the timezone fix.
	it( 'emits update:modelValue as ISO string with Z when input changes', async () => {
		const wrapper = newWrapper();

		await wrapper.find( 'input' ).setValue( '2025-06-15T14:00' );

		const events = wrapper.emitted( 'update:modelValue' );
		expect( events ).toBeTruthy();
		expect( events?.[ 0 ] ).toEqual( [ newStringValue( '2025-06-15T14:00:00Z' ) ] );
	} );

	it( 'emits undefined when input is cleared', async () => {
		const wrapper = newWrapper( {
			modelValue: newStringValue( '2025-06-15T14:00:00Z' ),
		} );

		await wrapper.find( 'input' ).setValue( '' );

		const events = wrapper.emitted( 'update:modelValue' );
		expect( events ).toBeTruthy();
		expect( events?.[ 0 ] ).toEqual( [ undefined ] );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns initial value', () => {
			const wrapper = newWrapper( {
				modelValue: newStringValue( '2025-06-15T14:00:00Z' ),
			} );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() )
				.toEqual( newStringValue( '2025-06-15T14:00:00Z' ) );
		} );

		// FIXME(#728): pins the TZ-naive Z-suffix round-trip through getCurrentValue; revisit with the timezone fix.
		it( 'returns updated value after input', async () => {
			const wrapper = newWrapper();

			await wrapper.find( 'input' ).setValue( '2030-03-20T09:15' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() )
				.toEqual( newStringValue( '2030-03-20T09:15:00Z' ) );
		} );

		it( 'returns undefined for empty input', async () => {
			const wrapper = newWrapper( {
				modelValue: newStringValue( '2025-06-15T14:00:00Z' ),
			} );

			await wrapper.find( 'input' ).setValue( '' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toBeUndefined();
		} );

		// TODO(#728): add a proper TZ-pinned round-trip test here. It belongs with the
		// fix for the timezone bug: set TZ to a non-UTC zone via vi.stubEnv and verify
		// that an explicit-offset ISO input (e.g. "2025-06-15T23:30:00+05:00") survives
		// a round-trip without being silently reinterpreted as UTC. Not added now because
		// the current implementation is pure string manipulation (TZ-independent) and a
		// TZ-pinned test would either pass trivially or document the bug as correct.
		// Note: vi.stubEnv('TZ', ...) only affects process.env — Node's Intl/Date honor
		// the TZ at startup in older versions, so this may need a jsdom-level shim or
		// a fresh worker depending on the Node version the project builds against.
	} );
} );
