import { VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it } from 'vitest';
import MonolingualTextInput from '@/components/Value/MonolingualTextInput.vue';
import { type MonolingualTextValue, newMonolingualTextValue, ValueType } from '@/domain/Value';
import {
	type MonolingualTextProperty,
	newMonolingualTextProperty,
} from '@/domain/propertyTypes/MonolingualText';
import { ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { createTestWrapper, setupMwMock } from '../../VueTestHelpers.ts';

describe( 'MonolingualTextInput', () => {

	function newWrapper(
		props: Partial<ValueInputProps<MonolingualTextProperty>> = {},
	): VueWrapper<InstanceType<typeof MonolingualTextInput>> {
		return createTestWrapper( MonolingualTextInput, {
			modelValue: undefined,
			label: 'Original title',
			property: newMonolingualTextProperty( { name: 'Original title', multiple: true } ),
			...props,
		} );
	}

	function textInputs( wrapper: VueWrapper ): ReturnType<VueWrapper['findAll']> {
		return wrapper.findAll( '.ext-neowiki-monolingual-text-input__text input' );
	}

	function languageInputs( wrapper: VueWrapper ): ReturnType<VueWrapper['findAll']> {
		return wrapper.findAll( '.ext-neowiki-monolingual-text-input__language input' );
	}

	function lastEmittedValue( wrapper: VueWrapper ): MonolingualTextValue | undefined {
		const emitted = wrapper.emitted( 'update:modelValue' );
		return emitted === undefined ?
			undefined :
			emitted[ emitted.length - 1 ][ 0 ] as MonolingualTextValue | undefined;
	}

	function rowElements( wrapper: VueWrapper ): ReturnType<VueWrapper['findAll']> {
		return wrapper.findAll( '.ext-neowiki-monolingual-text-input > div' );
	}

	function rowMessages( wrapper: VueWrapper ): ( string | undefined )[] {
		return rowElements( wrapper ).map(
			( row ) => row.find( '.cdx-message' ).exists() ? row.find( '.cdx-message' ).text() : undefined,
		);
	}

	function textValues( wrapper: VueWrapper ): string[] {
		return textInputs( wrapper ).map( ( input ) => ( input.element as HTMLInputElement ).value );
	}

	/**
	 * Leaves a row the way the browser reports it: the element focus lands on is what says whether
	 * the row was left at all, `null` standing for focus leaving the page.
	 */
	async function leaveRow( wrapper: VueWrapper, index: number, into: Element | null ): Promise<void> {
		await rowElements( wrapper )[ index ].trigger( 'focusout', { relatedTarget: into } );
	}

	// Codex gives its "no results" element the menu-item class too, so it is excluded here.
	function languageMenuItems( wrapper: VueWrapper ): ReturnType<VueWrapper['findAll']> {
		return wrapper.findAll( '.ext-neowiki-monolingual-text-input__language' )[ 0 ]
			.findAll( '.cdx-menu-item:not( .cdx-menu__no-results )' );
	}

	/**
	 * Types into the first row's language field and picks the first entry the menu then offers,
	 * which is the only way a language is committed.
	 */
	async function chooseLanguage( wrapper: VueWrapper, query: string ): Promise<void> {
		await languageInputs( wrapper )[ 0 ].setValue( query );
		await languageMenuItems( wrapper )[ 0 ].trigger( 'click' );
	}

	beforeEach( () => {
		setupMwMock( {
			config: { wgUserLanguage: 'en' },
			languageNames: { en: 'English', eu: 'Basque', es: 'Spanish' },
		} );
	} );

	it( 'opens no row of its own on a single-valued property', () => {
		const wrapper = newWrapper( {
			property: newMonolingualTextProperty( { name: 'Original title', multiple: false } ),
			modelValue: newMonolingualTextValue( [ { text: 'Zinema', language: 'eu' } ] ),
		} );

		expect( textValues( wrapper ) ).toEqual( [ 'Zinema' ] );
	} );

	it( 'edits every part a single-valued property already holds', () => {
		const wrapper = newWrapper( {
			property: newMonolingualTextProperty( { name: 'Original title', multiple: false } ),
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		expect( textValues( wrapper ) ).toEqual( [ 'Zinema', 'Cine' ] );
	} );

	it( 'reports every part of a single-valued property it was never asked to change', () => {
		const wrapper = newWrapper( {
			property: newMonolingualTextProperty( { name: 'Original title', multiple: false } ),
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		expect( wrapper.vm.getCurrentValue() ).toEqual( {
			type: ValueType.MonolingualText,
			parts: [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			],
		} );
	} );

	it( 'shows each stored part plus the empty row the next one is typed into', () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		expect( textValues( wrapper ) ).toEqual( [ 'Zinema', 'Cine', '' ] );
	} );

	it( 'emits a typed text tagged with the interface language', async () => {
		setupMwMock( {
			config: { wgUserLanguage: 'eu', wgContentLanguage: 'en' },
			languageNames: { en: 'English', eu: 'Basque', es: 'Spanish' },
		} );
		const wrapper = newWrapper();

		await textInputs( wrapper )[ 0 ].setValue( 'Zinema' );

		expect( lastEmittedValue( wrapper ) ).toEqual( {
			type: ValueType.MonolingualText,
			parts: [ { text: 'Zinema', language: 'eu' } ],
		} );
	} );

	it( 'opens another empty row once the trailing one is typed into', async () => {
		const wrapper = newWrapper();

		await textInputs( wrapper )[ 0 ].setValue( 'Zinema' );

		expect( textInputs( wrapper ) ).toHaveLength( 2 );
	} );

	it( 'opens no further row when a row above the trailing one is typed into', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		await textInputs( wrapper )[ 0 ].setValue( 'Zinemak' );

		expect( textInputs( wrapper ) ).toHaveLength( 3 );
	} );

	it( 'shows the parts of a value set from outside', async () => {
		const wrapper = newWrapper( { modelValue: newMonolingualTextValue( [ { text: 'Zinema', language: 'eu' } ] ) } );

		await wrapper.setProps( { modelValue: newMonolingualTextValue( [ { text: 'Kino', language: 'de' } ] ) } );

		expect( textValues( wrapper ) ).toEqual( [ 'Kino', '' ] );
	} );

	it( 'keeps the rows being edited when its own value comes back', async () => {
		const wrapper = newWrapper( { modelValue: newMonolingualTextValue( [ { text: 'Zinema', language: 'eu' } ] ) } );

		await textInputs( wrapper )[ 1 ].setValue( 'Cine' );
		await textInputs( wrapper )[ 0 ].setValue( '' );
		await wrapper.setProps( { modelValue: lastEmittedValue( wrapper ) } );

		expect( textValues( wrapper ) ).toEqual( [ '', 'Cine', '' ] );
	} );

	it( 'opens no further row for a text that is only spaces', async () => {
		const wrapper = newWrapper();

		await textInputs( wrapper )[ 0 ].setValue( '   ' );

		expect( textInputs( wrapper ) ).toHaveLength( 1 );
	} );

	it( 'keeps a single-valued property at one row however much is typed', async () => {
		const wrapper = newWrapper( {
			property: newMonolingualTextProperty( { name: 'Original title', multiple: false } ),
		} );

		await textInputs( wrapper )[ 0 ].setValue( 'Zinema' );

		expect( textInputs( wrapper ) ).toHaveLength( 1 );
	} );

	it( 'emits no value once the last text is cleared', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [ { text: 'Zinema', language: 'eu' } ] ),
		} );

		await textInputs( wrapper )[ 0 ].setValue( '' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toEqual( [ [ undefined ] ] );
	} );

	it( 'drops a cleared row the moment focus reaches another row', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
				{ text: 'Kino', language: 'de' },
			] ),
		} );

		await textInputs( wrapper )[ 1 ].setValue( '' );
		await leaveRow( wrapper, 1, textInputs( wrapper )[ 2 ].element );

		expect( textValues( wrapper ) ).toEqual( [ 'Zinema', 'Kino', '' ] );
	} );

	it( 'keeps a row holding text once the user leaves it', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		await leaveRow( wrapper, 0, textInputs( wrapper )[ 1 ].element );

		expect( textValues( wrapper ) ).toEqual( [ 'Zinema', 'Cine', '' ] );
	} );

	it( 'keeps a cleared row while focus is on its language field', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		await textInputs( wrapper )[ 0 ].setValue( '' );
		await leaveRow( wrapper, 0, languageInputs( wrapper )[ 0 ].element );

		expect( textValues( wrapper ) ).toEqual( [ '', 'Cine', '' ] );
	} );

	it( 'drops a cleared row when tabbing lands on its language menu', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		await textInputs( wrapper )[ 0 ].setValue( '' );
		await languageInputs( wrapper )[ 0 ].setValue( 'Spa' );
		await leaveRow( wrapper, 0, rowElements( wrapper )[ 0 ].find( '.cdx-menu' ).element );

		expect( textValues( wrapper ) ).toEqual( [ 'Cine', '' ] );
	} );

	it( 'drops a cleared row of a single-valued property the user leaves', async () => {
		const wrapper = newWrapper( {
			property: newMonolingualTextProperty( { name: 'Original title', multiple: false } ),
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		// The last row, which on a multi-valued property would be the trailing one the next part is
		// typed into. A single-valued property has no such row, so nothing keeps this one.
		await textInputs( wrapper )[ 1 ].setValue( '' );
		await leaveRow( wrapper, 1, textInputs( wrapper )[ 0 ].element );

		expect( textValues( wrapper ) ).toEqual( [ 'Zinema' ] );
	} );

	it( 'keeps the one row a single-valued property has left, empty as it is', async () => {
		const wrapper = newWrapper( {
			property: newMonolingualTextProperty( { name: 'Original title', multiple: false } ),
			modelValue: newMonolingualTextValue( [ { text: 'Zinema', language: 'eu' } ] ),
		} );

		await textInputs( wrapper )[ 0 ].setValue( '' );
		await leaveRow( wrapper, 0, null );

		expect( textValues( wrapper ) ).toEqual( [ '' ] );
	} );

	it( 'keeps the trailing row the next part is typed into', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [ { text: 'Zinema', language: 'eu' } ] ),
		} );

		await leaveRow( wrapper, 1, null );

		expect( textValues( wrapper ) ).toEqual( [ 'Zinema', '' ] );
	} );

	it( 'leaves the field the user moved into showing the row they moved into', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
				{ text: 'Kino', language: 'de' },
			] ),
		} );
		document.body.appendChild( wrapper.element as HTMLElement );

		// The first row is blanked and left for the third: dropping it moves every row below it up
		// one position, and the field the user is now in has to travel with its row.
		await textInputs( wrapper )[ 0 ].setValue( '' );
		const movedInto = textInputs( wrapper )[ 2 ].element as HTMLInputElement;
		movedInto.focus();
		await leaveRow( wrapper, 0, movedInto );

		expect( ( document.activeElement as HTMLInputElement ).value ).toBe( 'Kino' );
	} );

	it( 'stores the tag of the language chosen by its name', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [ { text: 'Zinema', language: 'en' } ] ),
		} );

		await chooseLanguage( wrapper, 'Basq' );

		expect( lastEmittedValue( wrapper )?.parts ).toEqual( [ { text: 'Zinema', language: 'eu' } ] );
	} );

	it( 'reports no change for a language chosen on a row holding no text', async () => {
		const wrapper = newWrapper();

		await chooseLanguage( wrapper, 'Basq' );

		expect( wrapper.emitted( 'update:modelValue' ) ).toBeUndefined();
	} );

	it( 'shows a server violation under the row it names', () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Ci', language: 'es' },
			] ),
			serverViolations: [
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 1 },
			],
		} );

		expect( rowMessages( wrapper ) ).toEqual( [ undefined, 'neowiki-field-min-length3', undefined ] );
	} );

	it( 'shows a violation under the row holding the part it names, not the row at that position', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
				{ text: 'Ci', language: 'fr' },
			] ),
			serverViolations: [
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 1 },
			],
		} );

		// Blanking the first row takes it out of the value, so the part the violation names is now the
		// second one, while the row the user is still in stays on screen above the part it displaced.
		await textInputs( wrapper )[ 0 ].setValue( '' );

		expect( rowMessages( wrapper ) ).toEqual( [ undefined, undefined, 'neowiki-field-min-length3', undefined ] );
	} );

	it( 'shows a single-valued property its violation once, under the field', () => {
		const wrapper = newWrapper( {
			property: newMonolingualTextProperty( { name: 'Original title', multiple: false } ),
			modelValue: newMonolingualTextValue( [ { text: 'Ci', language: 'es' } ] ),
			serverViolations: [
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 0 },
			],
		} );

		expect( wrapper.text().match( /neowiki-field-min-length3/g ) ).toHaveLength( 1 );
	} );

	it( 'shows a violation that names no part under the whole field', () => {
		const wrapper = newWrapper( {
			serverViolations: [
				{ propertyName: 'Original title', code: 'required', args: [], severity: 'error', valuePartIndex: null },
			],
		} );

		expect( wrapper.text() ).toContain( 'neowiki-field-required' );
	} );

	it( 'asks the parent to drop every indexed violation once a part appears or disappears', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Ci', language: 'es' },
			] ),
			serverViolations: [
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 1 },
			],
		} );

		await textInputs( wrapper )[ 0 ].setValue( '' );

		expect( wrapper.emitted( 'clear-server-violation' ) )
			.toEqual( [ [ { propertyName: 'Original title', valuePartIndex: 1 } ] ] );
	} );

	it( 'asks the parent to drop the violation of the row being edited, and only that one', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zi', language: 'eu' },
				{ text: 'Ci', language: 'es' },
				{ text: 'Ki', language: 'de' },
			] ),
			serverViolations: [
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 0 },
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 1 },
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 2 },
			],
		} );

		await textInputs( wrapper )[ 1 ].setValue( 'Cine' );

		expect( wrapper.emitted( 'clear-server-violation' ) )
			.toEqual( [ [ { propertyName: 'Original title', valuePartIndex: 1 } ] ] );
	} );

	it( 'reports what it holds when the parent asks on save', async () => {
		const wrapper = newWrapper();

		await textInputs( wrapper )[ 0 ].setValue( 'Zinema' );

		expect( wrapper.vm.getCurrentValue() ).toEqual( {
			type: ValueType.MonolingualText,
			parts: [ { text: 'Zinema', language: 'en' } ],
		} );
	} );

} );
