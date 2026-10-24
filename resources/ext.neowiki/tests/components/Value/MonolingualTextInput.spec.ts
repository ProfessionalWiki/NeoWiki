import { enableAutoUnmount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import MonolingualTextInput from '@/components/Value/MonolingualTextInput.vue';
import { type MonolingualTextValue, newMonolingualTextValue, ValueType } from '@/domain/Value';
import {
	type MonolingualTextProperty,
	newMonolingualTextProperty,
} from '@/domain/propertyTypes/MonolingualText';
import { ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import {
	chooseFromLanguageList,
	createTestWrapper,
	pickInLanguageSearch,
	setupMwMock,
	shownLanguage,
} from '../../VueTestHelpers.ts';

// Every row's language picker listens to the document while it is open, so no mount is left standing.
enableAutoUnmount( afterEach );

describe( 'MonolingualTextInput', () => {

	const singleValued = newMonolingualTextProperty( { name: 'Original title', multiple: false } );

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

	function rowLanguages( wrapper: VueWrapper ): string[] {
		return rowElements( wrapper ).map( ( row ) => shownLanguage( row.find( '.ext-neowiki-language-input' ).element ) );
	}

	function languageControlNames( wrapper: VueWrapper ): ( string | undefined )[] {
		return rowElements( wrapper ).map(
			( row ) => row.find( '.ext-neowiki-language-input [role="combobox"]' ).attributes( 'aria-label' ),
		);
	}

	function lastEmittedValue( wrapper: VueWrapper ): MonolingualTextValue | undefined {
		const emitted = wrapper.emitted( 'update:modelValue' );
		return emitted === undefined ?
			undefined :
			emitted[ emitted.length - 1 ][ 0 ] as MonolingualTextValue | undefined;
	}

	function rowElements( wrapper: VueWrapper ): ReturnType<VueWrapper['findAll']> {
		return wrapper.findAll( '.ext-neowiki-monolingual-text-input__row' );
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

	async function chooseFromList( wrapper: VueWrapper, index: number, language: string ): Promise<void> {
		await chooseFromLanguageList( rowElements( wrapper )[ index ], language );
	}

	async function openLanguageSearch( wrapper: VueWrapper, index: number ): Promise<void> {
		await chooseFromList( wrapper, index, 'Other language…' );
	}

	async function chooseLanguage( wrapper: VueWrapper, query: string ): Promise<void> {
		await openLanguageSearch( wrapper, 0 );
		await pickInLanguageSearch( rowElements( wrapper )[ 0 ], query );
	}

	// The messages render as they do in a wiki, so an assertion on a label reads as what a user hears.
	function setUpWiki( dataLanguages: string[] = [] ): void {
		setupMwMock( {
			config: {
				wgUserLanguage: 'en',
				wgContentLanguage: 'en',
				wgNeoWikiDataLanguages: dataLanguages,
			},
			messages: {
				'neowiki-monolingual-text-text-label': ( property, position ) => `${ property } text ${ position }`,
				'neowiki-monolingual-text-language-label': ( property, position ) =>
					`${ property } language ${ position }`,
				'neowiki-language-input-other': 'Other language…',
			},
		} );
	}

	beforeEach( () => {
		setUpWiki();
		// jsdom reports a page without focus, which is the user being away in another tab.
		vi.spyOn( document, 'hasFocus' ).mockReturnValue( true );
	} );

	afterEach( () => {
		vi.restoreAllMocks();
	} );

	it( 'edits every part a single-valued property already holds', () => {
		const wrapper = newWrapper( {
			property: singleValued,
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		expect( textValues( wrapper ) ).toEqual( [ 'Zinema', 'Cine' ] );
	} );

	it( 'reports every part of a single-valued property it was never asked to change', () => {
		const wrapper = newWrapper( {
			property: singleValued,
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

	it( 'shows each stored part under its own language, plus the empty row the next one is typed into', () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		expect( textValues( wrapper ) ).toEqual( [ 'Zinema', 'Cine', '' ] );
		expect( rowLanguages( wrapper ) ).toEqual( [ 'Basque', 'Spanish', 'English' ] );
	} );

	it( 'names both of a row\'s fields after the row they belong to', () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [ { text: 'Zinema', language: 'eu' } ] ),
		} );

		expect( textInputs( wrapper ).map( ( input ) => input.attributes( 'aria-label' ) ) )
			.toEqual( [ 'Original title text 1', 'Original title text 2' ] );
		expect( languageControlNames( wrapper ) )
			.toEqual( [ 'Original title language 1', 'Original title language 2' ] );
	} );

	it( 'shows the new row in the first data language the value lacks', () => {
		setUpWiki( [ 'de', 'fr', 'it' ] );

		const wrapper = newWrapper( { modelValue: newMonolingualTextValue( [ { text: 'Kino', language: 'de' } ] ) } );

		expect( rowLanguages( wrapper ) ).toEqual( [ 'German', 'French' ] );
	} );

	it( 'emits a part typed into the new row in the first data language the value lacks', async () => {
		setUpWiki( [ 'de', 'fr', 'it' ] );
		const wrapper = newWrapper( { modelValue: newMonolingualTextValue( [ { text: 'Kino', language: 'de' } ] ) } );

		await textInputs( wrapper )[ 1 ].setValue( 'Cinéma' );

		expect( lastEmittedValue( wrapper )?.parts ).toEqual( [
			{ text: 'Kino', language: 'de' },
			{ text: 'Cinéma', language: 'fr' },
		] );
	} );

	it( 'moves the new row on to the data language a part leaves', async () => {
		setUpWiki( [ 'de', 'fr', 'it' ] );
		const wrapper = newWrapper( { modelValue: newMonolingualTextValue( [ { text: 'Kino', language: 'de' } ] ) } );

		await chooseFromList( wrapper, 0, 'French' );
		await textInputs( wrapper )[ 1 ].setValue( 'Film' );

		expect( lastEmittedValue( wrapper )?.parts ).toEqual( [
			{ text: 'Kino', language: 'fr' },
			{ text: 'Film', language: 'de' },
		] );
	} );

	it( 'keeps the language picked for the new row when the value changes', async () => {
		setUpWiki( [ 'de', 'fr', 'it' ] );
		const wrapper = newWrapper( { modelValue: newMonolingualTextValue( [ { text: 'Kino', language: 'de' } ] ) } );

		await chooseFromList( wrapper, 1, 'Italian' );
		await chooseFromList( wrapper, 0, 'French' );
		await textInputs( wrapper )[ 1 ].setValue( 'Cinema' );

		expect( lastEmittedValue( wrapper )?.parts[ 1 ] ).toEqual( { text: 'Cinema', language: 'it' } );
	} );

	it( 'keeps a typed part in its language when another part changes language', async () => {
		setUpWiki( [ 'de', 'fr', 'it' ] );
		const wrapper = newWrapper( { modelValue: newMonolingualTextValue( [ { text: 'Kino', language: 'de' } ] ) } );

		await textInputs( wrapper )[ 1 ].setValue( 'Cinéma' );
		await chooseFromList( wrapper, 0, 'Italian' );

		expect( lastEmittedValue( wrapper )?.parts ).toEqual( [
			{ text: 'Kino', language: 'it' },
			{ text: 'Cinéma', language: 'fr' },
		] );
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

	it( 'drops the trailing row once the property becomes single-valued, keeping what is being typed', async () => {
		const wrapper = newWrapper( { modelValue: newMonolingualTextValue( [ { text: 'Zinema', language: 'eu' } ] ) } );

		await textInputs( wrapper )[ 1 ].setValue( 'Cine' );
		await wrapper.setProps( { property: singleValued } );

		expect( textValues( wrapper ) ).toEqual( [ 'Zinema', 'Cine' ] );
	} );

	it( 'opens no further row for a text that is only spaces', async () => {
		const wrapper = newWrapper();

		await textInputs( wrapper )[ 0 ].setValue( '   ' );

		expect( textInputs( wrapper ) ).toHaveLength( 1 );
	} );

	it( 'keeps a single-valued property at one row however much is typed', async () => {
		const wrapper = newWrapper( {
			property: singleValued,
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

	it( 'keeps a cleared row while focus is in the language search it opened', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		await textInputs( wrapper )[ 0 ].setValue( '' );
		await openLanguageSearch( wrapper, 0 );
		await leaveRow(
			wrapper,
			0,
			rowElements( wrapper )[ 0 ].find( '.ext-neowiki-language-picker__search input' ).element,
		);

		expect( textValues( wrapper ) ).toEqual( [ '', 'Cine', '' ] );
	} );

	it( 'drops a cleared row of a single-valued property the user leaves', async () => {
		const wrapper = newWrapper( {
			property: singleValued,
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
			property: singleValued,
			modelValue: newMonolingualTextValue( [ { text: 'Zinema', language: 'eu' } ] ),
		} );

		await textInputs( wrapper )[ 0 ].setValue( '' );
		await leaveRow( wrapper, 0, null );

		expect( textValues( wrapper ) ).toEqual( [ '' ] );
	} );

	it( 'drops a cleared row when focus leaves it for no other field', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		await textInputs( wrapper )[ 0 ].setValue( '' );
		await leaveRow( wrapper, 0, null );

		expect( textValues( wrapper ) ).toEqual( [ 'Cine', '' ] );
	} );

	it( 'keeps a cleared row while the user is away in another tab or application', async () => {
		vi.spyOn( document, 'hasFocus' ).mockReturnValue( false );
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zinema', language: 'eu' },
				{ text: 'Cine', language: 'es' },
			] ),
		} );

		await textInputs( wrapper )[ 0 ].setValue( '' );
		await leaveRow( wrapper, 0, null );

		expect( textValues( wrapper ) ).toEqual( [ '', 'Cine', '' ] );
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
		document.body.appendChild( wrapper.element );

		// The first row is blanked and left for the third: dropping it moves every row below it up
		// one position, and the field the user is now in has to travel with its row.
		await textInputs( wrapper )[ 0 ].setValue( '' );
		const movedInto = textInputs( wrapper )[ 2 ].element as HTMLInputElement;
		movedInto.focus();
		await leaveRow( wrapper, 0, movedInto );

		expect( ( document.activeElement as HTMLInputElement ).value ).toBe( 'Kino' );
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

	it( 'shows a multi-valued property its part\'s violation under that row only, not under the field too', () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [ { text: 'Ci', language: 'es' } ] ),
			serverViolations: [
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 0 },
			],
		} );

		expect( rowMessages( wrapper ) ).toEqual( [ 'neowiki-field-min-length3', undefined ] );
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

	it( 'shows a single-valued property its violation once, under the field', () => {
		const wrapper = newWrapper( {
			property: singleValued,
			modelValue: newMonolingualTextValue( [ { text: 'Ci', language: 'es' } ] ),
			serverViolations: [
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 0 },
			],
		} );

		expect( wrapper.text().match( /neowiki-field-min-length3/g ) ).toHaveLength( 1 );
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

	it( 'asks the parent to drop the violation of a part whose language changes, and only that one', async () => {
		const wrapper = newWrapper( {
			modelValue: newMonolingualTextValue( [
				{ text: 'Zi', language: 'eu' },
				{ text: 'Ci', language: 'es' },
			] ),
			serverViolations: [
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 0 },
				{ propertyName: 'Original title', code: 'min-length', args: [ 3 ], severity: 'error', valuePartIndex: 1 },
			],
		} );

		await chooseFromList( wrapper, 1, 'English' );

		expect( wrapper.emitted( 'clear-server-violation' ) )
			.toEqual( [ [ { propertyName: 'Original title', valuePartIndex: 1 } ] ] );
	} );

	it( 'asks the parent to drop every indexed violation when a language changes where each language takes one value', async () => {
		setUpWiki( [ 'en', 'de', 'pt' ] );
		const wrapper = newWrapper( {
			property: newMonolingualTextProperty( { name: 'Original title', multiple: true, uniqueLanguages: true } ),
			modelValue: newMonolingualTextValue( [
				{ text: 'Eiffel Tower at dusk', language: 'en' },
				{ text: 'Eiffel Tower at sunset', language: 'en' },
				{ text: 'Torre Eiffel', language: 'pt' },
			] ),
			serverViolations: [ {
				propertyName: 'Original title',
				code: 'single-value-per-language',
				args: [],
				severity: 'error',
				valuePartIndex: 1,
			} ],
		} );

		// The first text leaves English, which settles the violation of the second one.
		await chooseFromList( wrapper, 0, 'German' );

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
