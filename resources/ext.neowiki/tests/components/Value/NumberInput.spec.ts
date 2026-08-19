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
		interface NativeNumberField {
			reportBadInput(): Promise<void>;
			reportValue( text: string ): Promise<void>;
			valueWrites: string[];
		}

		/**
		 * Models what a browser does with a native number input, which jsdom does not:
		 * text it cannot parse ("5foo") stays on screen while `value` reads empty and
		 * `validity.badInput` is set, and any assignment to `value` replaces that text
		 * and clears the flag. jsdom sanitizes `element.value = '5foo'` to '' and never
		 * sets badInput, so `valueWrites` stands in for the text on screen: an empty
		 * `valueWrites` means the characters the user typed are still there.
		 */
		function bindNativeNumberField( wrapper: VueWrapper ): NativeNumberField {
			const input = wrapper.find( 'input' );
			const element = input.element as HTMLInputElement;
			const descriptor = Object.getOwnPropertyDescriptor( Object.getPrototypeOf( element ), 'value' )!;
			const valueWrites: string[] = [];
			let badInput = false;

			function write( text: string ): void {
				badInput = false;
				descriptor.set!.call( element, text );
			}

			Object.defineProperty( element, 'value', {
				configurable: true,
				get: () => descriptor.get!.call( element ),
				set: ( text: string ) => {
					valueWrites.push( text );
					write( text );
				},
			} );
			Object.defineProperty( element, 'validity', {
				configurable: true,
				get: () => ( { badInput } ),
			} );

			return {
				async reportBadInput(): Promise<void> {
					// The browser reports an empty value for text it cannot parse. Written
					// through the raw setter so it is not counted as a write by Vue.
					descriptor.set!.call( element, '' );
					badInput = true;
					await input.trigger( 'input' );
				},
				async reportValue( text: string ): Promise<void> {
					write( text );
					await input.trigger( 'input' );
				},
				valueWrites,
			};
		}

		function unparseableInputMessage( wrapper: VueWrapper ): string | null {
			return ( wrapper.vm as unknown as ValueInputExposes ).unparseableInputMessage!();
		}

		it( 'shows the invalid number message as a field error', async () => {
			const wrapper = newWrapper();
			const field = bindNativeNumberField( wrapper );

			await field.reportBadInput();

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
			expect( wrapper.findComponent( CdxField ).props( 'messages' ) )
				.toEqual( { error: 'neowiki-field-invalid-number' } );
		} );

		it( 'drops the invalid number message once the text parses again', async () => {
			const wrapper = newWrapper();
			const field = bindNativeNumberField( wrapper );
			await field.reportBadInput();

			await field.reportValue( '5' );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
			expect( wrapper.findComponent( CdxField ).props( 'messages' ) ).toEqual( {} );
		} );

		it( 'reports unparseable input so the save can be held', async () => {
			const wrapper = newWrapper();
			const field = bindNativeNumberField( wrapper );

			await field.reportBadInput();

			expect( unparseableInputMessage( wrapper ) ).toBe( 'neowiki-field-invalid-number' );
		} );

		// The save gates show what they are given, so a message that diverged from the
		// one on screen would send the user looking for an error the field never showed.
		it( 'exposes the same message the field renders', async () => {
			const wrapper = newWrapper();
			const field = bindNativeNumberField( wrapper );

			await field.reportBadInput();

			expect( wrapper.findComponent( CdxField ).props( 'messages' ) )
				.toEqual( { error: unparseableInputMessage( wrapper ) } );
		} );

		it( 'stops reporting unparseable input once the text parses again', async () => {
			const wrapper = newWrapper();
			const field = bindNativeNumberField( wrapper );
			await field.reportBadInput();

			await field.reportValue( '5' );

			expect( unparseableInputMessage( wrapper ) ).toBeNull();
		} );

		// Selecting all and deleting is the natural recovery from bad text: badInput
		// drops while the reported value stays '', so only the native input event —
		// not a model-value change — is there to clear the flag.
		it( 'stops reporting unparseable input when the user clears the field', async () => {
			const wrapper = newWrapper();
			const field = bindNativeNumberField( wrapper );
			await field.reportBadInput();

			await field.reportValue( '' );

			expect( unparseableInputMessage( wrapper ) ).toBeNull();
		} );

		// A value that renders as the text already bound leaves the widget's DOM
		// untouched, so the unparseable text is still on screen and still unsavable.
		it( 'keeps reporting unparseable input when the parent supplies an empty value', async () => {
			const wrapper = newWrapper();
			const field = bindNativeNumberField( wrapper );
			await field.reportBadInput();

			await wrapper.setProps( { modelValue: undefined } );

			expect( unparseableInputMessage( wrapper ) ).toBe( 'neowiki-field-invalid-number' );
			expect( field.valueWrites ).toEqual( [] );
		} );

		// The editor dialogs outlive the subject being edited, so a field left in this
		// state must not keep blocking saves once a different value is loaded into it.
		it( 'stops reporting unparseable input when the parent supplies a new value', async () => {
			const wrapper = newWrapper();
			const field = bindNativeNumberField( wrapper );
			await field.reportBadInput();

			await wrapper.setProps( { modelValue: newNumberValue( 7 ) } );

			expect( unparseableInputMessage( wrapper ) ).toBeNull();
			expect( field.valueWrites ).toEqual( [ '7' ] );
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
			const field = bindNativeNumberField( wrapper );

			await field.reportBadInput();

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
