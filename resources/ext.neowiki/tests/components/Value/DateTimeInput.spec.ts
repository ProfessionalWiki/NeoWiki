import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { cdxIconClock } from '@wikimedia/codex-icons';
import { newStringValue } from '@/domain/Value';
import DateTimeInput from '@/components/Value/DateTimeInput.vue';
import { newDateTimeProperty, DateTimeProperty } from '@/domain/propertyTypes/DateTime';
import { ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';
import { toLocalInputValue } from '@/domain/propertyTypes/dateTimeConversion';

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

	it( 'renders a CdxTextInput with datetime-local input-type and clock start-icon', () => {
		const wrapper = newWrapper();

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.find( 'input[type="datetime-local"]' ).exists() ).toBe( true );

		const textInput = wrapper.findComponent( CdxTextInput );
		expect( textInput.exists() ).toBe( true );
		expect( textInput.props( 'inputType' ) ).toBe( 'datetime-local' );
		expect( textInput.props( 'startIcon' ) ).toBe( cdxIconClock );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'renders exactly one input and it is under a CdxTextInput', () => {
		const wrapper = newWrapper();

		const inputs = wrapper.findAll( 'input' );
		expect( inputs.length ).toBe( 1 );
		expect( inputs[ 0 ].element.closest( '.cdx-text-input' ) ).not.toBeNull();
	} );

	it( 'displays ISO modelValue as the host-local wall-clock in the input', () => {
		const iso = '2025-06-15T14:00:00Z';
		const wrapper = newWrapper( { modelValue: newStringValue( iso ) } );

		expect( wrapper.find( 'input' ).element.value ).toBe( toLocalInputValue( iso ) );
	} );

	it( 'displays empty input when modelValue is undefined', () => {
		const wrapper = newWrapper( { modelValue: undefined } );

		expect( wrapper.find( 'input' ).element.value ).toBe( '' );
	} );

	it( 'passes minimum and maximum as host-local wall-clock on the input min/max attrs', () => {
		const minimum = '2020-01-01T00:00:00Z';
		const maximum = '2030-12-31T23:59:59Z';
		const wrapper = newWrapper( {
			property: newDateTimeProperty( { minimum, maximum } ),
		} );

		expect( wrapper.find( 'input' ).attributes( 'min' ) ).toBe( toLocalInputValue( minimum ) );
		expect( wrapper.find( 'input' ).attributes( 'max' ) ).toBe( toLocalInputValue( maximum ) );
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

	it( 'emits update:modelValue as a UTC ISO representing the typed local instant', async () => {
		const wrapper = newWrapper();
		const local = '2025-06-15T14:00';
		const expectedIso = new Date( local ).toISOString();

		await wrapper.find( 'input' ).setValue( local );

		const events = wrapper.emitted( 'update:modelValue' );
		expect( events?.[ 0 ] ).toEqual( [ newStringValue( expectedIso ) ] );
	} );

	it( 'emits undefined when input is cleared', async () => {
		const wrapper = newWrapper( {
			modelValue: newStringValue( '2025-06-15T14:00:00Z' ),
		} );

		await wrapper.find( 'input' ).setValue( '' );

		const events = wrapper.emitted( 'update:modelValue' );
		expect( events?.[ 0 ] ).toEqual( [ undefined ] );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns the initial modelValue as the same-instant ISO', () => {
			const iso = '2025-06-15T14:00:00Z';
			const wrapper = newWrapper( { modelValue: newStringValue( iso ) } );

			const result = ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue();

			if ( result === undefined || result.type !== 'string' ) {
				throw new Error( 'expected a string value' );
			}
			const resultIso = result.parts[ 0 ];
			expect( new Date( resultIso ).getTime() ).toBe( new Date( iso ).getTime() );
		} );

		it( 'returns the typed local instant as the corresponding UTC ISO', async () => {
			const wrapper = newWrapper();
			const local = '2030-03-20T09:15';
			const expectedIso = new Date( local ).toISOString();

			await wrapper.find( 'input' ).setValue( local );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() )
				.toEqual( newStringValue( expectedIso ) );
		} );

		it( 'returns undefined for empty input', async () => {
			const wrapper = newWrapper( {
				modelValue: newStringValue( '2025-06-15T14:00:00Z' ),
			} );

			await wrapper.find( 'input' ).setValue( '' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toBeUndefined();
		} );

		it( 'preserves an explicit-offset modelValue as the same instant', () => {
			const iso = '2025-06-15T23:30:00+05:00';
			const wrapper = newWrapper( { modelValue: newStringValue( iso ) } );

			const result = ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue();

			if ( result === undefined || result.type !== 'string' ) {
				throw new Error( 'expected a string value' );
			}
			const resultIso = result.parts[ 0 ];
			expect( new Date( resultIso ).getTime() ).toBe( new Date( iso ).getTime() );
		} );
	} );

	describe( 'under a non-UTC TZ (#728 pinning test)', () => {
		it( 'round-trips a UTC ISO through the input preserving the instant', async () => {
			// vi.stubEnv sets process.env.TZ. Node's Intl/Date may cache the
			// startup TZ, so this documents intent; the assertion itself is
			// TZ-invariant — it checks instant equivalence, not a literal string.
			vi.stubEnv( 'TZ', 'Europe/Berlin' );

			const iso = '2025-06-15T14:00:00Z';
			const wrapper = newWrapper( { modelValue: newStringValue( iso ) } );

			const localValue = wrapper.find( 'input' ).element.value;
			await wrapper.find( 'input' ).setValue( localValue );

			const events = wrapper.emitted( 'update:modelValue' );
			const firstEvent = events?.[ 0 ]?.[ 0 ];
			if ( firstEvent === undefined || firstEvent === null || typeof firstEvent !== 'object' || !( 'parts' in firstEvent ) ) {
				throw new Error( 'expected an emitted string value' );
			}
			const emittedIso = ( firstEvent as { parts: string[] } ).parts[ 0 ];

			expect( new Date( emittedIso ).getTime() ).toBe( new Date( iso ).getTime() );

			vi.unstubAllEnvs();
		} );
	} );
} );
