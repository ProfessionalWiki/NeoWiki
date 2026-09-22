import { DOMWrapper, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { CdxField } from '@wikimedia/codex';
import { newStringValue, StringValue } from '@/domain/Value';
import DateInput from '@/components/Value/DateInput.vue';
import { newDateProperty, DateProperty } from '@/domain/propertyTypes/Date';
import { ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers.ts';

describe( 'DateInput', () => {
	beforeEach( () => {
		mockMw( 'en' );
	} );

	function mockMw( language: string ): void {
		setupMwMock( {
			config: { wgUserLanguage: language },
			messages: {
				'neowiki-field-unreadable-date': ( text: string ) => `not a date: ${ text }`,
				'neowiki-field-invalid-day': ( days: string ) => `no such day, the month has ${ days }`,
			},
			functions: [ 'config', 'message', 'language' ],
		} );
	}

	afterEach( () => {
		vi.restoreAllMocks();
		delete ( HTMLInputElement.prototype as { showPicker?: unknown } ).showPicker;
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

	const textInput = ( wrapper: VueWrapper ): DOMWrapper<HTMLInputElement> =>
		wrapper.find<HTMLInputElement>( '.ext-neowiki-date-text-input__text input' );

	const footer = ( wrapper: VueWrapper ): string =>
		wrapper.find( '.ext-neowiki-date-text-input__footer' ).exists() ?
			wrapper.find( '.ext-neowiki-date-text-input__footer' ).text() :
			'';

	const edit = ( wrapper: VueWrapper ): Promise<void> => textInput( wrapper ).trigger( 'focusin' );

	const leave = ( wrapper: VueWrapper ): Promise<void> =>
		textInput( wrapper ).trigger( 'focusout', { relatedTarget: document.body } );

	const lastEmittedValue = ( wrapper: VueWrapper ): unknown => {
		const events = wrapper.emitted( 'update:modelValue' )!;
		return events[ events.length - 1 ][ 0 ];
	};

	const exposed = ( wrapper: VueWrapper ): ValueInputExposes => wrapper.vm as unknown as ValueInputExposes;

	const fieldMessages = ( wrapper: VueWrapper ): unknown => wrapper.findComponent( CdxField ).props( 'messages' );

	it( 'renders the label over one text field with the calendar icon', () => {
		const wrapper = newWrapper();

		expect( wrapper.text() ).toContain( 'Test Label' );
		expect( textInput( wrapper ).exists() ).toBe( true );
		expect( wrapper.find( '.cdx-text-input--has-start-icon' ).exists() ).toBe( true );
	} );

	it( 'shows a stored date the way the wiki displays it', () => {
		const wrapper = newWrapper( { modelValue: newStringValue( '2025-06' ) } );

		expect( textInput( wrapper ).element.value ).toBe( 'Jun 2025' );
	} );

	it( 'displays the date in the user\'s language', () => {
		mockMw( 'de' );

		const wrapper = newWrapper( { modelValue: newStringValue( '1984-06-15' ) } );

		expect( textInput( wrapper ).element.value ).toBe( '15. Juni 1984' );
	} );

	it( 'shows the stored form where the field could not read the displayed form back', () => {
		mockMw( 'eu' );

		const wrapper = newWrapper( { modelValue: newStringValue( '1984-06-15' ) } );

		expect( textInput( wrapper ).element.value ).toBe( '1984-06-15' );
		expect( exposed( wrapper ).getCurrentValue() ).toEqual( newStringValue( '1984-06-15' ) );
	} );

	it( 'names the text field by the label', () => {
		const wrapper = newWrapper();

		expect( wrapper.find( 'label' ).attributes( 'for' ) ).toBe( textInput( wrapper ).attributes( 'id' ) );
	} );

	it( 'shows an empty field when modelValue is undefined', () => {
		expect( textInput( newWrapper( { modelValue: undefined } ) ).element.value ).toBe( '' );
	} );

	it( 'shows a newly passed modelValue', async () => {
		const wrapper = newWrapper( { modelValue: newStringValue( '1984' ) } );

		await wrapper.setProps( { modelValue: newStringValue( '1990-03' ) } );

		expect( textInput( wrapper ).element.value ).toBe( 'Mar 1990' );
	} );

	describe( 'what the property asks for', () => {
		it( 'says a year is enough while an empty field with no minPrecision is edited', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );

			expect( footer( wrapper ) ).toContain( 'neowiki-date-input-hint-year' );
		} );

		it( 'says a full date is needed while an empty field with minPrecision day is edited', async () => {
			const wrapper = newWrapper( { property: newDateProperty( { minPrecision: 'day' } ) } );

			await edit( wrapper );

			expect( footer( wrapper ) ).toContain( 'neowiki-date-input-hint-day' );
		} );

		it( 'says nothing under an empty field that is not being edited', () => {
			expect( newWrapper().text() ).not.toContain( 'neowiki-date-input-hint' );
		} );

		it( 'drops the hint once the field holds a date', async () => {
			const wrapper = newWrapper( { modelValue: newStringValue( '1984' ) } );

			await edit( wrapper );

			expect( wrapper.text() ).not.toContain( 'neowiki-date-input-hint' );
		} );

		it( 'shows neither hint nor footer under text it cannot read', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '198x' );

			expect( wrapper.find( '.ext-neowiki-date-text-input__footer' ).exists() ).toBe( false );
		} );

		it( 'shows neither hint nor footer under an empty field that has a message', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { name: 'Foo', required: true } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'required', args: [], severity: 'error', valuePartIndex: null },
				],
			} );

			await edit( wrapper );

			expect( wrapper.find( '.ext-neowiki-date-text-input__footer' ).exists() ).toBe( false );
			expect( wrapper.text() ).not.toContain( 'neowiki-date-input-hint' );
		} );

		it( 'does not flag an empty required date before the user has typed', () => {
			const wrapper = newWrapper( { property: newDateProperty( { required: true } ) } );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'default' );
			expect( fieldMessages( wrapper ) ).toEqual( {} );
		} );
	} );

	describe( 'reading what is typed', () => {
		it.each( [
			[ 'a year', '1984', '1984' ],
			[ 'a year and month', '1984-06', '1984-06' ],
			[ 'a full date', '15 June 1984', '1984-06-15' ],
		] )( 'emits %s as its stored form', async ( _description: string, typed: string, iso: string ) => {
			const wrapper = newWrapper();

			await textInput( wrapper ).setValue( typed );

			expect( lastEmittedValue( wrapper ) ).toEqual( newStringValue( iso ) );
		} );

		it( 'shows the stored form while the field is edited, for checking and copying', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( 'June 1984' );

			expect( wrapper.find( '.ext-neowiki-date-text-input__stored' ).text() ).toBe( 'neowiki-date-input-stored-as1984-06' );
		} );

		it( 'keeps the footer while it is being selected, so it can be copied', async () => {
			const wrapper = newWrapper( { modelValue: newStringValue( '1984-06' ) } );

			await edit( wrapper );
			const footerElement = wrapper.find( '.ext-neowiki-date-text-input__footer' ).element;
			await textInput( wrapper ).trigger( 'focusout', { relatedTarget: footerElement } );

			expect( footer( wrapper ) ).toContain( '1984-06' );
		} );

		it( 'shows no footer while the field is not being edited', () => {
			expect( footer( newWrapper( { modelValue: newStringValue( '1984-06' ) } ) ) ).toBe( '' );
		} );

		it( 'hides the footer again when focus leaves the field', async () => {
			const wrapper = newWrapper( { modelValue: newStringValue( '1984-06' ) } );

			await edit( wrapper );
			await textInput( wrapper ).trigger( 'focusout', { relatedTarget: document.body } );

			expect( footer( wrapper ) ).toBe( '' );
		} );

		it( 'emits undefined when the field is cleared', async () => {
			const wrapper = newWrapper( { modelValue: newStringValue( '1984' ) } );

			await textInput( wrapper ).setValue( '' );

			expect( lastEmittedValue( wrapper ) ).toBeUndefined();
		} );

		it( 'keeps the text as typed when the parent echoes the emitted value back', async () => {
			const wrapper = newWrapper();

			await textInput( wrapper ).setValue( 'June 1984' );
			await wrapper.setProps( { modelValue: lastEmittedValue( wrapper ) as StringValue } );

			expect( textInput( wrapper ).element.value ).toBe( 'June 1984' );
		} );

		it( 'rewrites the text to the display form when focus leaves the field', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '15 june 1984' );
			await textInput( wrapper ).trigger( 'focusout', { relatedTarget: document.body } );

			expect( textInput( wrapper ).element.value ).toBe( 'Jun 15, 1984' );
			expect( lastEmittedValue( wrapper ) ).toEqual( newStringValue( '1984-06-15' ) );
		} );

		it( 'keeps text it cannot read when focus leaves the field', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '198x' );
			await textInput( wrapper ).trigger( 'focusout', { relatedTarget: document.body } );

			expect( textInput( wrapper ).element.value ).toBe( '198x' );
		} );

		it( 'settles an ambiguous date on the chosen reading when focus leaves the field', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '06/07/1984' );
			await wrapper.find( '.ext-neowiki-date-text-input__alternative' ).trigger( 'click' );
			await textInput( wrapper ).trigger( 'focusout', { relatedTarget: document.body } );
			await edit( wrapper );

			expect( textInput( wrapper ).element.value ).toBe( 'Jun 7, 1984' );
			expect( wrapper.find( '.ext-neowiki-date-text-input__alternative' ).exists() ).toBe( false );
		} );

		it( 'empties the field when the modelValue becomes undefined', async () => {
			const wrapper = newWrapper( { modelValue: newStringValue( '1984-06' ) } );

			await wrapper.setProps( { modelValue: undefined } );

			expect( textInput( wrapper ).element.value ).toBe( '' );
		} );
	} );

	describe( 'a date that reads two ways', () => {
		it( 'reads it day first and offers the other reading', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '06/07/1984' );

			expect( lastEmittedValue( wrapper ) ).toEqual( newStringValue( '1984-07-06' ) );
			expect( wrapper.find( '.ext-neowiki-date-text-input__alternative' ).text() ).toContain( 'Jun 7, 1984' );
		} );

		it( 'emits the other reading when it is taken', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '06/07/1984' );
			await wrapper.find( '.ext-neowiki-date-text-input__alternative' ).trigger( 'click' );

			expect( lastEmittedValue( wrapper ) ).toEqual( newStringValue( '1984-06-07' ) );
			expect( wrapper.find( '.ext-neowiki-date-text-input__alternative' ).text() ).toContain( 'Jul 6, 1984' );
		} );

		it( 'reads the next ambiguous date day first again after the other reading was taken', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '06/07/1984' );
			await wrapper.find( '.ext-neowiki-date-text-input__alternative' ).trigger( 'click' );
			await textInput( wrapper ).setValue( '07/08/1984' );

			expect( lastEmittedValue( wrapper ) ).toEqual( newStringValue( '1984-08-07' ) );
		} );

		it( 'offers nothing for a date that reads one way', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '15/06/1984' );

			expect( wrapper.find( '.ext-neowiki-date-text-input__alternative' ).exists() ).toBe( false );
		} );

		it( 'keeps the footer while focus moves to its own button', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '06/07/1984' );
			const button = wrapper.find( '.ext-neowiki-date-text-input__alternative' );
			await textInput( wrapper ).trigger( 'focusout', { relatedTarget: button.element } );

			expect( footer( wrapper ) ).toContain( 'neowiki-date-input-alternative' );
		} );
	} );

	describe( 'text that does not read as a date', () => {
		it( 'says nothing while it is still being typed', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '2026-0' );

			expect( fieldMessages( wrapper ) ).toEqual( {} );
			expect( exposed( wrapper ).unparseableInputMessage!() ).not.toBeNull();
		} );

		it( 'says so at the field once the field is left, and emits undefined', async () => {
			const wrapper = newWrapper( { modelValue: newStringValue( '1984' ) } );

			await edit( wrapper );
			await textInput( wrapper ).setValue( 'next tuesday' );
			await leave( wrapper );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
			expect( fieldMessages( wrapper ) ).toEqual( { error: 'not a date: next tuesday' } );
			expect( lastEmittedValue( wrapper ) ).toBeUndefined();
		} );

		it.each( [
			[ 'a month beyond the twelfth', '1984-13', 'neowiki-field-invalid-month' ],
			[ 'the year zero', '0000', 'neowiki-field-year-zero' ],
		] )( 'says what is wrong with %s', async ( _description: string, typed: string, message: string ) => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( typed );
			await leave( wrapper );

			expect( fieldMessages( wrapper ) ).toEqual( { error: message } );
		} );

		it( 'says how many days the month has for a day it does not have', async () => {
			const wrapper = newWrapper();

			await edit( wrapper );
			await textInput( wrapper ).setValue( '30.2.1985' );
			await leave( wrapper );

			expect( fieldMessages( wrapper ) ).toEqual( { error: 'no such day, the month has 28' } );
		} );

		it( 'keeps the text on screen when the parent echoes the undefined back', async () => {
			const wrapper = newWrapper( { modelValue: newStringValue( '1984' ) } );

			await textInput( wrapper ).setValue( '198x' );
			await wrapper.setProps( { modelValue: undefined } );

			expect( textInput( wrapper ).element.value ).toBe( '198x' );
		} );

		it( 'reports the message it shows, so the save is held', async () => {
			const wrapper = newWrapper();

			await textInput( wrapper ).setValue( '198x' );

			expect( exposed( wrapper ).unparseableInputMessage!() ).toBe( 'not a date: 198x' );
		} );

		it( 'reports no message while the text reads as a date', () => {
			expect( exposed( newWrapper( { modelValue: newStringValue( '1984' ) } ) ).unparseableInputMessage!() ).toBeNull();
		} );

		it( 'outranks a server violation', async () => {
			const wrapper = newWrapper( {
				modelValue: newStringValue( '1984' ),
				property: newDateProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'min-precision-day', args: [], severity: 'warning', valuePartIndex: null },
				],
			} );

			await edit( wrapper );
			await textInput( wrapper ).setValue( '198x' );
			await leave( wrapper );

			expect( fieldMessages( wrapper ) ).toEqual( { error: 'not a date: 198x' } );
		} );
	} );

	describe( 'the browser\'s calendar', () => {
		const fullDateProperty = (): DateProperty => newDateProperty( { minPrecision: 'day' } );

		it( 'offers no button where the browser cannot show a picker', () => {
			expect( newWrapper( { property: fullDateProperty() } ).find( '.ext-neowiki-date-text-input__pick' ).exists() ).toBe( false );
		} );

		it( 'offers no button for a property that takes a year or a month, which the picker cannot give', () => {
			( HTMLInputElement.prototype as { showPicker?: unknown } ).showPicker = vi.fn();

			expect( newWrapper().find( '.ext-neowiki-date-text-input__pick' ).exists() ).toBe( false );
			expect( newWrapper( { property: newDateProperty( { minPrecision: 'month' } ) } ).find( '.ext-neowiki-date-text-input__pick' ).exists() ).toBe( false );
		} );

		it( 'opens the browser\'s picker for a property that takes full dates only', async () => {
			const showPicker = vi.fn();
			( HTMLInputElement.prototype as { showPicker?: unknown } ).showPicker = showPicker;
			const wrapper = newWrapper( { property: fullDateProperty() } );

			await wrapper.find( '.ext-neowiki-date-text-input__pick' ).trigger( 'click' );

			expect( showPicker ).toHaveBeenCalledOnce();
		} );

		it( 'takes the picked full date into the field', async () => {
			( HTMLInputElement.prototype as { showPicker?: unknown } ).showPicker = vi.fn();
			const wrapper = newWrapper( { modelValue: newStringValue( '1984' ), property: fullDateProperty() } );

			await wrapper.find( '.ext-neowiki-date-text-input__native' ).setValue( '1984-06-15' );
			await leave( wrapper );

			expect( textInput( wrapper ).element.value ).toBe( 'Jun 15, 1984' );
			expect( lastEmittedValue( wrapper ) ).toEqual( newStringValue( '1984-06-15' ) );
		} );

		it( 'opens the picker on the date the field holds', () => {
			( HTMLInputElement.prototype as { showPicker?: unknown } ).showPicker = vi.fn();
			const wrapper = newWrapper( { modelValue: newStringValue( '1984-06-15' ), property: fullDateProperty() } );

			expect( wrapper.find<HTMLInputElement>( '.ext-neowiki-date-text-input__native' ).element.value ).toBe( '1984-06-15' );
		} );

		it( 'follows the property as it changes to and from taking full dates only', async () => {
			( HTMLInputElement.prototype as { showPicker?: unknown } ).showPicker = vi.fn();
			const wrapper = newWrapper();

			await wrapper.setProps( { property: fullDateProperty() } );
			expect( wrapper.find( '.ext-neowiki-date-text-input__pick' ).exists() ).toBe( true );

			await wrapper.setProps( { property: newDateProperty( { minPrecision: 'month' } ) } );
			expect( wrapper.find( '.ext-neowiki-date-text-input__pick' ).exists() ).toBe( false );
		} );
	} );

	describe( 'getCurrentValue', () => {
		it( 'returns the initial modelValue', () => {
			expect( exposed( newWrapper( { modelValue: newStringValue( '2025-06-15' ) } ) ).getCurrentValue() )
				.toEqual( newStringValue( '2025-06-15' ) );
		} );

		it( 'returns the date the text reads as', async () => {
			const wrapper = newWrapper();

			await textInput( wrapper ).setValue( 'March 2030' );

			expect( exposed( wrapper ).getCurrentValue() ).toEqual( newStringValue( '2030-03' ) );
		} );

		it( 'returns undefined for an empty field', async () => {
			const wrapper = newWrapper( { modelValue: newStringValue( '2025' ) } );

			await textInput( wrapper ).setValue( '' );

			expect( exposed( wrapper ).getCurrentValue() ).toBeUndefined();
		} );
	} );

	describe( 'Server violations', () => {
		it( 'shows a field-level server violation as the field error', () => {
			const wrapper = newWrapper( {
				modelValue: newStringValue( '2025-06-15' ),
				property: newDateProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'type-mismatch', args: [ 'date', 'number' ], severity: 'error', valuePartIndex: null },
				],
			} );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'error' );
			expect( fieldMessages( wrapper ) ).toHaveProperty( 'error' );
		} );

		it( 'still surfaces a server-sourced required violation on the date field', () => {
			const wrapper = newWrapper( {
				modelValue: newStringValue( '2025-06-15' ),
				property: newDateProperty( { name: 'Foo', required: true } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'required', args: [], severity: 'error', valuePartIndex: null },
				],
			} );

			expect( fieldMessages( wrapper ) ).toHaveProperty( 'error', 'neowiki-field-required' );
		} );

		it( 'shows a warning violation with the warning status', () => {
			const wrapper = newWrapper( {
				modelValue: newStringValue( '2025' ),
				property: newDateProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'min-precision-day', args: [], severity: 'warning', valuePartIndex: null },
				],
			} );

			expect( wrapper.findComponent( CdxField ).props( 'status' ) ).toBe( 'warning' );
			expect( fieldMessages( wrapper ) ).toHaveProperty( 'warning', 'neowiki-field-min-precision-day' );
		} );

		it( 'emits clear-server-violation when the user edits the field', async () => {
			const wrapper = newWrapper( {
				property: newDateProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'type-mismatch', args: [ 'date', 'number' ], severity: 'error', valuePartIndex: null },
				],
			} );

			await textInput( wrapper ).setValue( '2025' );

			expect( wrapper.emitted( 'clear-server-violation' )![ 0 ] ).toEqual( [
				{ propertyName: 'Foo', valuePartIndex: null },
			] );
		} );

		it( 'passes server-violation args to mw.message as formatted strings, not the raw ISO', () => {
			const minimum = '2025-01-01';
			newWrapper( {
				property: newDateProperty( { name: 'Foo' } ),
				serverViolations: [
					{ propertyName: 'Foo', code: 'min-value', args: [ minimum ], severity: 'error', valuePartIndex: null },
				],
			} );

			const minCall = findMessageCall( 'neowiki-field-min-value' );
			expect( minCall?.[ 1 ] ).not.toBe( minimum );
			expect( minCall?.[ 1 ] ).not.toMatch( /^\d{4}-\d{2}-\d{2}$/ );
		} );
	} );
} );
