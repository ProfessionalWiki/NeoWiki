import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { cdxIconCalendar } from '@wikimedia/codex-icons';
import { newStringValue, StringValue, ValueType } from '@/domain/Value';
import DateInput from '@/components/Value/DateInput.vue';
import { newDateProperty, DateProperty } from '@/domain/propertyTypes/Date';
import { ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';

describe( 'DateInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str,
			} ) ),
		} );
	} );

	function newWrapper( props: Partial<ValueInputProps<DateProperty>> = {} ): VueWrapper {
		return createTestWrapper( DateInput, {
			modelValue: undefined,
			label: 'Test Label',
			property: newDateProperty( {} ),
			...props,
		} );
	}

	function findMessageCall( key: string ): unknown[] | undefined {
		const calls = ( mw.message as ReturnType<typeof vi.fn> ).mock.calls as unknown[][];
		return calls.find( ( call ) => call[ 0 ] === key );
	}

	it( 'renders a CdxTextInput with date input-type and calendar start-icon', () => {
		const wrapper = newWrapper();

		expect( wrapper.findComponent( CdxField ).exists() ).toBe( true );
		expect( wrapper.find( 'input[type="date"]' ).exists() ).toBe( true );

		const textInput = wrapper.findComponent( CdxTextInput );
		expect( textInput.exists() ).toBe( true );
		expect( textInput.props( 'inputType' ) ).toBe( 'date' );
		expect( textInput.props( 'startIcon' ) ).toBe( cdxIconCalendar );
		expect( wrapper.text() ).toContain( 'Test Label' );
	} );

	it( 'displays the stored date string in the input', () => {
		const wrapper = newWrapper( { modelValue: newStringValue( '2025-06-15' ) } );

		expect( wrapper.find( 'input' ).element.value ).toBe( '2025-06-15' );
	} );

	it( 'displays empty input when modelValue is undefined', () => {
		const wrapper = newWrapper( { modelValue: undefined } );

		expect( wrapper.find( 'input' ).element.value ).toBe( '' );
	} );

	it( 'passes minimum and maximum on the input min/max attrs', () => {
		const wrapper = newWrapper( {
			property: newDateProperty( { minimum: '2020-01-01', maximum: '2030-12-31' } ),
		} );

		expect( wrapper.find( 'input' ).attributes( 'min' ) ).toBe( '2020-01-01' );
		expect( wrapper.find( 'input' ).attributes( 'max' ) ).toBe( '2030-12-31' );
	} );

	it( 'does not flag an empty required date before the user has picked a value', () => {
		const wrapper = newWrapper( {
			modelValue: undefined,
			property: newDateProperty( { required: true } ),
		} );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual( {} );
	} );

	it( 'still surfaces a server-sourced required violation on the date field', () => {
		// A valid value is supplied so live validation cannot itself emit
		// 'required'; the surfaced error therefore proves the server path.
		const wrapper = newWrapper( {
			modelValue: newStringValue( '2025-06-15' ),
			property: newDateProperty( { name: 'Foo', required: true } ),
			serverViolations: [
				{ propertyName: 'Foo', code: 'required', args: [], valuePartIndex: null },
			],
		} );

		expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
		expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-required' );
	} );

	it( 'emits update:modelValue as the typed date string', async () => {
		const wrapper = newWrapper();

		await wrapper.find( 'input' ).setValue( '2025-06-15' );

		const events = wrapper.emitted( 'update:modelValue' );
		expect( events?.[ 0 ] ).toEqual( [ newStringValue( '2025-06-15' ) ] );
	} );

	it( 'emits undefined when input is cleared', async () => {
		const wrapper = newWrapper( {
			modelValue: newStringValue( '2025-06-15' ),
		} );

		await wrapper.find( 'input' ).setValue( '' );

		const events = wrapper.emitted( 'update:modelValue' );
		expect( events?.[ 0 ] ).toEqual( [ undefined ] );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns the initial modelValue date string', () => {
			const wrapper = newWrapper( { modelValue: newStringValue( '2025-06-15' ) } );

			const result = ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue();

			if ( result === undefined || result.type !== ValueType.String ) {
				throw new Error( 'expected a string value' );
			}
			expect( result.parts[ 0 ] ).toBe( '2025-06-15' );
		} );

		it( 'returns the typed date string', async () => {
			const wrapper = newWrapper();

			await wrapper.find( 'input' ).setValue( '2030-03-20' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() )
				.toEqual( newStringValue( '2030-03-20' ) );
		} );

		it( 'returns undefined for empty input', async () => {
			const wrapper = newWrapper( {
				modelValue: newStringValue( '2025-06-15' ),
			} );

			await wrapper.find( 'input' ).setValue( '' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toBeUndefined();
		} );
	} );

	it( 'round-trips a date modelValue through the full input event flow unchanged', async () => {
		const date = '2025-06-15';
		const wrapper = newWrapper( { modelValue: newStringValue( date ) } );

		const displayed = wrapper.find( 'input' ).element.value;
		await wrapper.find( 'input' ).setValue( displayed );

		const events = wrapper.emitted( 'update:modelValue' );
		const firstEvent = events?.[ 0 ]?.[ 0 ] as StringValue | undefined;
		if ( firstEvent === undefined || firstEvent.type !== ValueType.String ) {
			throw new Error( 'expected an emitted string value' );
		}

		expect( firstEvent.parts[ 0 ] ).toBe( date );
	} );

	describe( 'Server violations', () => {
		it( 'shows a field-level server violation as the field error', () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'type-mismatch', args: [ 'date', 'number' ], valuePartIndex: null },
				],
			} );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
			expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-type-mismatch' );
		} );

		it( 'emits clear-server-violation when the user edits the field', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'type-mismatch', args: [ 'date', 'number' ], valuePartIndex: null },
				],
			} );

			await wrapper.find( 'input' ).setValue( '2025-06-15' );

			expect( wrapper.emitted( 'clear-server-violation' )![ 0 ] ).toEqual( [
				{ propertyName: 'Foo', valuePartIndex: null },
			] );
		} );

		it( 'passes server-violation args to mw.message as formatted strings, not the raw ISO', () => {
			const minimum = '2025-01-01';
			newWrapper( {
				property: newDateProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'min-value', args: [ minimum ], valuePartIndex: null },
				],
			} );

			const minCall = findMessageCall( 'neowiki-field-min-value' );
			expect( minCall?.[ 1 ] ).not.toBe( minimum );
			expect( minCall?.[ 1 ] ).not.toMatch( /^\d{4}-\d{2}-\d{2}$/ );
		} );
	} );
} );
