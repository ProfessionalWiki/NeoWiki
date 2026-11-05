import { enableAutoUnmount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it } from 'vitest';
import PropertyLabelsInput, { type PropertyLabelsInputExposes } from '@/components/SchemaEditor/Property/PropertyLabelsInput.vue';
import { announcedLanguage, createTestWrapper, setupMwMock } from '../../../VueTestHelpers.ts';

// Every row's language picker listens to the document while it is open, so no mount is left standing.
enableAutoUnmount( afterEach );

describe( 'PropertyLabelsInput', () => {

	function useDataLanguages( ...languages: string[] ): void {
		setupMwMock( { config: { wgNeoWikiDataLanguages: languages } } );
	}

	beforeEach( () => {
		useDataLanguages( 'en', 'de' );
	} );

	// Passes the labels emitted back in, as the property editor does.
	function newWrapper( labels?: Record<string, string> ): VueWrapper {
		const wrapper: VueWrapper = createTestWrapper( PropertyLabelsInput, {
			labels,
			'onUpdate:labels': ( emitted?: Record<string, string> ) => wrapper.setProps( { labels: emitted } ),
		} );

		return wrapper;
	}

	function rows( wrapper: VueWrapper ): ReturnType<VueWrapper['findAll']> {
		return wrapper.findAll( '.ext-neowiki-monolingual-text-input__row' );
	}

	function rowTexts( wrapper: VueWrapper ): string[] {
		return rows( wrapper ).map( ( row ) => ( row.find( 'input' ).element as HTMLInputElement ).value );
	}

	function rowLanguages( wrapper: VueWrapper ): string[] {
		return rows( wrapper ).map( ( row ) => announcedLanguage( row.find( '.ext-neowiki-language-input' ).element ) );
	}

	function rowMessages( wrapper: VueWrapper ): ( string | undefined )[] {
		return rows( wrapper ).map( ( row ) => row.find( '.cdx-message' ).exists() ? row.find( '.cdx-message' ).text() : undefined );
	}

	async function typeInRow( wrapper: VueWrapper, index: number, text: string ): Promise<void> {
		await rows( wrapper )[ index ].find( 'input' ).setValue( text );
	}

	function lastEmittedLabels( wrapper: VueWrapper ): Record<string, string> | undefined {
		const emitted = wrapper.emitted( 'update:labels' );
		return emitted![ emitted!.length - 1 ][ 0 ] as Record<string, string> | undefined;
	}

	function unparseableInputMessage( wrapper: VueWrapper ): string | null {
		return ( wrapper.vm as unknown as PropertyLabelsInputExposes ).unparseableInputMessage();
	}

	it( 'shows each label in its language, with a row for the next one', () => {
		const wrapper = newWrapper( { en: 'Date of birth' } );

		expect( rowTexts( wrapper ) ).toEqual( [ 'Date of birth', '' ] );
		expect( rowLanguages( wrapper ) ).toEqual( [ 'English', 'German' ] );
	} );

	it( 'emits the labels by language as they are typed', async () => {
		const wrapper = newWrapper( { en: 'Date of birth' } );

		await typeInRow( wrapper, 1, 'Geburtsdatum' );

		expect( lastEmittedLabels( wrapper ) ).toEqual( { en: 'Date of birth', de: 'Geburtsdatum' } );
	} );

	it( 'emits no labels once the last one is cleared', async () => {
		const wrapper = newWrapper( { en: 'Date of birth' } );

		await typeInRow( wrapper, 0, '' );

		expect( lastEmittedLabels( wrapper ) ).toBeUndefined();
	} );

	describe( 'a second label in a language', () => {
		beforeEach( () => {
			useDataLanguages( 'en' );
		} );

		async function typeSecondEnglishLabel(): Promise<VueWrapper> {
			const wrapper = newWrapper( { en: 'Date of birth' } );
			await typeInRow( wrapper, 1, 'Birthday' );

			return wrapper;
		}

		it( 'is said to be one too many, under that label', async () => {
			const wrapper = await typeSecondEnglishLabel();

			expect( rowMessages( wrapper ) ).toEqual( [ undefined, 'neowiki-field-single-value-per-language', undefined ] );
		} );

		it( 'stays on screen', async () => {
			const wrapper = await typeSecondEnglishLabel();

			expect( rowTexts( wrapper ) ).toEqual( [ 'Date of birth', 'Birthday', '' ] );
		} );

		it( 'is input the save has to wait for', async () => {
			const wrapper = await typeSecondEnglishLabel();

			expect( unparseableInputMessage( wrapper ) ).toBe( 'neowiki-field-single-value-per-language' );
		} );
	} );

	it( 'reports nothing the save has to wait for while each language has one label', () => {
		const wrapper = newWrapper( { en: 'Date of birth', de: 'Geburtsdatum' } );

		expect( unparseableInputMessage( wrapper ) ).toBeNull();
	} );

} );
