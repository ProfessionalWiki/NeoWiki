import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField } from '@wikimedia/codex';
import { newNumberValue } from '@/domain/Value';
import NumberInput from '@/components/Value/NumberInput.vue';
import { newNumberProperty, NumberProperty } from '@/domain/propertyTypes/Number';
import { ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { createTestWrapper } from '../../VueTestHelpers.ts';

describe( 'NumberInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( str: string ) => ( {
				text: () => str,
				parse: () => str,
			} ) ),
		} );
	} );

	function newWrapper( props: Partial<ValueInputProps<NumberProperty>> = {} ): VueWrapper {
		return createTestWrapper( NumberInput, {
			modelValue: newNumberValue( 10 ),
			label: 'Test Label',
			property: newNumberProperty( {} ),
			...props,
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

	describe( 'getCurrentValue', () => {
		it( 'returns initial value', () => {
			const wrapper = newWrapper( {
				modelValue: newNumberValue( 42 ),
			} );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toEqual( newNumberValue( 42 ) );
		} );

		it( 'returns updated value after input', async () => {
			const wrapper = newWrapper( {
				modelValue: newNumberValue( 10 ),
			} );

			await wrapper.find( 'input' ).setValue( '99' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toEqual( newNumberValue( 99 ) );
		} );

		it( 'returns undefined for empty input', async () => {
			const wrapper = newWrapper( {
				modelValue: newNumberValue( 10 ),
			} );

			await wrapper.find( 'input' ).setValue( '' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toBeUndefined();
		} );

		it( 'returns undefined for non-numeric input', async () => {
			const wrapper = newWrapper( {
				modelValue: newNumberValue( 10 ),
			} );

			await wrapper.find( 'input' ).setValue( 'abc' );

			expect( ( wrapper.vm as unknown as ValueInputExposes ).getCurrentValue() ).toBeUndefined();
		} );
	} );

	describe( 'Unparseable input', () => {
		/**
		 * A native number input keeps text it cannot parse (say "5foo") visible in the
		 * widget while reporting an empty value to JavaScript, and flags the state
		 * through validity.badInput. jsdom never sets that flag from typing, so the
		 * browser's report is stubbed here.
		 */
		async function report( wrapper: VueWrapper, badInput: boolean, shownValue = '' ): Promise<void> {
			const input = wrapper.find( 'input' );
			const element = input.element as HTMLInputElement;
			element.value = shownValue;
			Object.defineProperty( element, 'validity', { value: { badInput }, configurable: true } );
			await input.trigger( 'input' );
		}

		function hasUnparseableInput( wrapper: VueWrapper ): boolean | undefined {
			return ( wrapper.vm as unknown as ValueInputExposes ).hasUnparseableInput!();
		}

		it( 'shows the invalid number message as a field error', async () => {
			const wrapper = newWrapper();

			await report( wrapper, true );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
			expect( wrapper.findComponent( CdxField ).props( 'messages' ) )
				.toEqual( { error: 'neowiki-field-invalid-number' } );
		} );

		it( 'drops the invalid number message once the text parses again', async () => {
			const wrapper = newWrapper();
			await report( wrapper, true );

			await report( wrapper, false, '5' );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
			expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual( {} );
		} );

		it( 'reports unparseable input so the save can be held', async () => {
			const wrapper = newWrapper();

			await report( wrapper, true );

			expect( hasUnparseableInput( wrapper ) ).toBe( true );
		} );

		it( 'stops reporting unparseable input once the text parses again', async () => {
			const wrapper = newWrapper();
			await report( wrapper, true );

			await report( wrapper, false, '5' );

			expect( hasUnparseableInput( wrapper ) ).toBe( false );
		} );

		// Selecting all and deleting is the natural recovery from bad text: badInput
		// drops while the reported value stays '', so only the native input event —
		// not a model-value change — is there to clear the flag.
		it( 'stops reporting unparseable input when the user clears the field', async () => {
			const wrapper = newWrapper();
			await report( wrapper, true );

			await report( wrapper, false );

			expect( hasUnparseableInput( wrapper ) ).toBe( false );
		} );

		// A value that renders as the text already bound leaves the widget's DOM
		// untouched, so the unparseable text is still on screen and still unsavable.
		it( 'keeps reporting unparseable input when the parent supplies an empty value', async () => {
			const wrapper = newWrapper();
			await report( wrapper, true );

			await wrapper.setProps( { modelValue: undefined } );

			expect( hasUnparseableInput( wrapper ) ).toBe( true );
		} );

		// The editor dialogs outlive the subject being edited, so a field left in this
		// state must not keep blocking saves once a different value is loaded into it.
		it( 'stops reporting unparseable input when the parent supplies a new value', async () => {
			const wrapper = newWrapper();
			await report( wrapper, true );

			await wrapper.setProps( { modelValue: newNumberValue( 7 ) } );

			expect( hasUnparseableInput( wrapper ) ).toBe( false );
		} );

		// The violation was raised against the value the backend was given, which is
		// not what the field is showing. A warning-severity violation is used because
		// an error-severity one would render the same status either way.
		it( 'shows the invalid number message in place of a server violation', async () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'max-value', args: [ '100' ], severity: 'warning', valuePartIndex: null },
				],
			} );

			await report( wrapper, true );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
			expect( wrapper.findComponent( CdxField ).props( 'messages' ) )
				.toEqual( { error: 'neowiki-field-invalid-number' } );
		} );
	} );

	describe( 'Server violations', () => {
		it( 'shows a field-level server violation as the field error', () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'type-mismatch', args: [ 'number', 'string' ], severity: 'error', valuePartIndex: null },
				],
			} );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
			expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'error', 'neowiki-field-type-mismatch' );
		} );

		it( 'shows a warning violation with the warning status', () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'max-value', args: [ '100' ], severity: 'warning', valuePartIndex: null },
				],
			} );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'warning' );
			expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toHaveProperty( 'warning', 'neowiki-field-max-value' );
		} );

		it( 'emits clear-server-violation when the user edits the field', async () => {
			const wrapper = newWrapper( {
				property: newNumberProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'type-mismatch', args: [ 'number', 'string' ], severity: 'error', valuePartIndex: null },
				],
			} );

			await wrapper.find( 'input' ).setValue( '12' );

			expect( wrapper.emitted( 'clear-server-violation' )![ 0 ] ).toEqual( [
				{ propertyName: 'Foo', valuePartIndex: null },
			] );
		} );
	} );
} );
