import { DOMWrapper, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import NeoNativeInput from '@/components/common/NeoNativeInput.vue';
import { reportPartlyClearedDate, reportUnparseableNumber, stubBadInput } from '../../VueTestHelpers.ts';

describe( 'NeoNativeInput', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			message: vi.fn( ( key: string ) => ( { text: () => key } ) ),
		} );
	} );

	function newWrapper( props: Partial<InstanceType<typeof NeoNativeInput>['$props']> = {} ): VueWrapper {
		return mount( NeoNativeInput, {
			props: {
				modelValue: '',
				inputType: 'number',
				...props,
			},
		} );
	}

	function lastUnparseableMessage( wrapper: VueWrapper ): unknown {
		const reported = wrapper.emitted( 'unparseable-change' ) ?? [];

		return reported[ reported.length - 1 ]?.[ 0 ];
	}

	function inputText( wrapper: VueWrapper ): string {
		return ( wrapper.find( 'input' ).element as HTMLInputElement ).value;
	}

	/**
	 * Clears the parts a partly cleared date input still shows with the given key, which leaves it
	 * empty without validity.badInput. Browsers fire no input event for this, only the key's release.
	 */
	async function clearRemainingDateParts( input: DOMWrapper<Element>, key: 'Backspace' | 'Delete' ): Promise<void> {
		stubBadInput( input, false );
		await input.trigger( 'keyup', { key } );
	}

	/**
	 * Types one part, such as the month, into an empty date input. Chromium then sets validity.badInput,
	 * but fires no input event, as the value stays empty: only the key's release tells.
	 */
	async function typeFirstDatePart( input: DOMWrapper<Element> ): Promise<void> {
		stubBadInput( input, true );
		await input.trigger( 'keyup', { key: '3' } );
	}

	async function typeReadableText( wrapper: VueWrapper, text: string ): Promise<void> {
		stubBadInput( wrapper.find( 'input' ), false );
		await wrapper.find( 'input' ).setValue( text );
	}

	it( 'reports the text the browser can read', async () => {
		const wrapper = newWrapper();

		await wrapper.find( 'input' ).setValue( '42' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ '42' ] ] );
	} );

	it( 'reports no text while the browser cannot read it', async () => {
		const wrapper = newWrapper( { modelValue: '100' } );

		await reportUnparseableNumber( wrapper.find( 'input' ) );

		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
	} );

	it.each( [
		[ 'number', reportUnparseableNumber, 'neowiki-field-invalid-number' ],
		[ 'date', reportPartlyClearedDate, 'neowiki-field-invalid-date' ],
		[ 'datetime-local', reportPartlyClearedDate, 'neowiki-field-invalid-datetime' ],
	] as const )( 'says a %s field holds text the browser cannot read', async ( inputType, report, message ) => {
		const wrapper = newWrapper( { inputType } );

		await report( wrapper.find( 'input' ) );

		expect( lastUnparseableMessage( wrapper ) ).toBe( message );
	} );

	it( 'withdraws the message once the browser can read the text again', async () => {
		const wrapper = newWrapper();
		await reportUnparseableNumber( wrapper.find( 'input' ) );

		await typeReadableText( wrapper, '5' );

		expect( lastUnparseableMessage( wrapper ) ).toBeNull();
	} );

	it( 'reports the text once the browser can read it again', async () => {
		const wrapper = newWrapper();
		await reportUnparseableNumber( wrapper.find( 'input' ) );

		await typeReadableText( wrapper, '5' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ '5' ] ] );
	} );

	// Showing an error re-renders the field, and a re-render writes the bound text into the input,
	// which in a browser replaces what the user typed.
	it( 'leaves the text it cannot read on screen when it re-renders', async () => {
		const wrapper = newWrapper( { modelValue: '100' } );
		await reportUnparseableNumber( wrapper.find( 'input' ) );

		await wrapper.setProps( { status: 'error' } );

		expect( inputText( wrapper ) ).toBe( '' );
	} );

	// A number field reports "1.0" as 1, which comes back as "1".
	it( 'leaves a number as it was typed when what it reported comes back', async () => {
		const wrapper = newWrapper();
		await wrapper.find( 'input' ).setValue( '1.0' );

		await wrapper.setProps( { modelValue: '1' } );
		await wrapper.setProps( { status: 'error' } );

		expect( inputText( wrapper ) ).toBe( '1.0' );
	} );

	it( 'shows a new value it is given in place of the text it cannot read', async () => {
		const wrapper = newWrapper( { modelValue: '100' } );
		await reportUnparseableNumber( wrapper.find( 'input' ) );

		await wrapper.setProps( { modelValue: '7' } );

		expect( inputText( wrapper ) ).toBe( '7' );
	} );

	it( 'withdraws the message when it is given a new value', async () => {
		const wrapper = newWrapper( { modelValue: '100' } );
		await reportUnparseableNumber( wrapper.find( 'input' ) );

		await wrapper.setProps( { modelValue: '7' } );

		expect( lastUnparseableMessage( wrapper ) ).toBeNull();
	} );

	// An empty value is what the input already holds, so nothing is written over the text on screen.
	it( 'keeps the message when it is given an empty value', async () => {
		const wrapper = newWrapper( { modelValue: '100' } );
		await reportUnparseableNumber( wrapper.find( 'input' ) );

		await wrapper.setProps( { modelValue: '' } );

		expect( lastUnparseableMessage( wrapper ) ).toBe( 'neowiki-field-invalid-number' );
	} );

	// A date field changes no value, and so fires no input event, when its first part is typed into it
	// empty or its last part is cleared: only the key's release tells.
	describe( 'Date parts', () => {
		const date = '2025-06-15';

		it( 'says it holds a date the browser cannot read once a first part is typed into it empty', async () => {
			const wrapper = newWrapper( { inputType: 'date' } );

			await typeFirstDatePart( wrapper.find( 'input' ) );

			expect( lastUnparseableMessage( wrapper ) ).toBe( 'neowiki-field-invalid-date' );
		} );

		it( 'reports the empty text once the last part is cleared', async () => {
			const wrapper = newWrapper( { inputType: 'date', modelValue: date } );
			await reportPartlyClearedDate( wrapper.find( 'input' ) );

			await clearRemainingDateParts( wrapper.find( 'input' ), 'Backspace' );

			expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ '' ] ] );
		} );

		it( 'withdraws the message once the last part is cleared', async () => {
			const wrapper = newWrapper( { inputType: 'date', modelValue: date } );
			await reportPartlyClearedDate( wrapper.find( 'input' ) );

			await clearRemainingDateParts( wrapper.find( 'input' ), 'Backspace' );

			expect( lastUnparseableMessage( wrapper ) ).toBeNull();
		} );

		it( 'withdraws the message once a part typed into it empty is cleared again', async () => {
			const wrapper = newWrapper( { inputType: 'date' } );
			await typeFirstDatePart( wrapper.find( 'input' ) );

			await clearRemainingDateParts( wrapper.find( 'input' ), 'Backspace' );

			expect( lastUnparseableMessage( wrapper ) ).toBeNull();
		} );

		it( 'reports nothing for a deletion key released in a field that still shows a date', async () => {
			const wrapper = newWrapper( { inputType: 'date', modelValue: date } );

			await wrapper.find( 'input' ).trigger( 'keyup', { key: 'Backspace' } );

			expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
		} );

		it( 'reports nothing for a deletion key released in a field that held no date', async () => {
			const wrapper = newWrapper( { inputType: 'date' } );

			await wrapper.find( 'input' ).trigger( 'keyup', { key: 'Backspace' } );

			expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
		} );

		// The browser shows nothing for a date it cannot display, such as one in the year 0. Tab's
		// release lands in the field it moved the focus to.
		it( 'keeps a date the field cannot show when Tab is released in it', async () => {
			const wrapper = newWrapper( { inputType: 'date', modelValue: '0000-01-01' } );

			await wrapper.find( 'input' ).trigger( 'keyup', { key: 'Tab' } );

			expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
		} );

		it( 'empties a date the field cannot show once a deletion key is released in it', async () => {
			const wrapper = newWrapper( { inputType: 'date', modelValue: '0000-01-01' } );

			await wrapper.find( 'input' ).trigger( 'keyup', { key: 'Delete' } );

			expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ '' ] ] );
		} );
	} );
} );
