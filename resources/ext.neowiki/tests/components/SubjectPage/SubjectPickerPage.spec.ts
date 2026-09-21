import { DOMWrapper, flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia } from 'pinia';
import { CdxLookup } from '@wikimedia/codex';
import SubjectPickerPage from '@/components/SubjectPage/SubjectPickerPage.vue';
import SubjectPicker from '@/components/common/SubjectPicker.vue';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';
import { Service } from '@/NeoWikiServices.ts';
import type { SubjectLabelSearch } from '@/domain/SubjectLabelSearch.ts';

const SUBJECT_ID = 's1demo1aaaaaaa1';

describe( 'SubjectPickerPage', () => {
	let subjectLabelSearch: SubjectLabelSearch;
	// Drained by afterEach, so a failed assertion cannot leave a node attached to the document for
	// the tests after it.
	const attachedWrappers: VueWrapper[] = [];

	// The real CdxLookup, so the field the page focuses and names is the one a browser renders.
	function mountPage( attachTo?: HTMLElement ): VueWrapper {
		return mount( SubjectPickerPage, {
			attachTo,
			global: {
				mocks: { $i18n: createI18nMock() },
				plugins: [ createPinia() ],
				provide: { [ Service.SubjectLabelSearch ]: subjectLabelSearch },
			},
		} );
	}

	beforeEach( () => {
		setupMwMock( { functions: [ 'util' ] } );

		subjectLabelSearch = {
			searchSubjectLabels: vi.fn().mockResolvedValue( [] ),
		};

		vi.stubGlobal( 'location', { href: '' } );
	} );

	afterEach( () => {
		attachedWrappers.splice( 0 ).forEach( ( wrapper ) => wrapper.unmount() );
		vi.unstubAllGlobals();
	} );

	// This page names no Schema, so a reader who knows only a Subject's label can reach it without
	// knowing what kind of thing it is.
	it( 'looks for Subjects of every Schema', async () => {
		const wrapper = mountPage();
		await flushPromises();

		// Only the field's value: the picker ignores CdxLookup's `input` event, which Codex also
		// emits for text it writes itself.
		wrapper.findComponent( CdxLookup ).vm.$emit( 'update:input-value', 'acme' );
		await flushPromises();

		expect( subjectLabelSearch.searchSubjectLabels ).toHaveBeenCalledWith( 'acme', undefined );
	} );

	// The page heading already says "Subject", so the field carries no visible label and needs a
	// name of its own to be announced by.
	// Asserted on the input rather than on CdxLookup: Codex forwards the attribute to the control
	// it wraps, which is what a screen reader reads.
	it( 'names the picker for a screen reader', () => {
		expect( mountPage().find( 'input' ).attributes( 'aria-label' ) )
			.toBe( 'neowiki-special-subject-picker-label' );
	} );

	// The field is what the page is for, so a reader can start typing without reaching for it.
	it( 'takes the focus on load', async () => {
		const wrapper = mountPage( document.body );
		attachedWrappers.push( wrapper );
		await flushPromises();

		expect( document.activeElement ).toBe( wrapper.find( 'input' ).element );
	} );

	// The page's script arrives after the skin's search box is usable, and a reader may be typing
	// there by then.
	it( 'leaves the focus where the reader already put it', async () => {
		const skinSearch = document.body.appendChild( document.createElement( 'input' ) );
		skinSearch.focus();

		try {
			attachedWrappers.push( mountPage( document.body ) );
			await flushPromises();

			expect( document.activeElement ).toBe( skinSearch );
		} finally {
			skinSearch.remove();
		}
	} );

	it( 'goes to the page of the Subject picked', async () => {
		const wrapper = mountPage();
		await flushPromises();

		wrapper.findComponent( SubjectPicker ).vm.$emit( 'update:selected', SUBJECT_ID );

		expect( location.href ).toBe( `/wiki/Special:Subject/${ SUBJECT_ID }` );
	} );

	it( 'goes to the Subject highlighted when Enter is pressed', async () => {
		const input = await highlightFirstSuggestion();

		await input.trigger( 'keydown', { key: 'Enter' } );

		expect( location.href ).toBe( `/wiki/Special:Subject/${ SUBJECT_ID }` );
	} );

	// Codex picks the highlighted suggestion on Tab, which in a relation field keeps the value the
	// reader was looking at. Here a pick leaves the page, and Tab is how a reader moves on.
	it( 'stays on the page when Tab leaves a highlighted suggestion', async () => {
		const input = await highlightFirstSuggestion();

		await input.trigger( 'keydown', { key: 'Tab' } );

		expect( location.href ).toBe( '' );
	} );

	async function highlightFirstSuggestion(): Promise<DOMWrapper<HTMLInputElement>> {
		vi.mocked( subjectLabelSearch.searchSubjectLabels ).mockResolvedValue( [
			{ id: SUBJECT_ID, label: 'ACME Inc.' },
			{ id: 's1demo1aaaaaaa2', label: 'ACME Labs' },
		] );

		const wrapper = mountPage( document.body );
		attachedWrappers.push( wrapper );
		await flushPromises();

		// Focused here rather than left to the page: Codex opens the menu only in a focused field.
		const input = wrapper.find<HTMLInputElement>( 'input' );
		input.element.focus();
		await input.setValue( 'acme' );
		await flushPromises();
		await input.trigger( 'keydown', { key: 'ArrowDown' } );

		return input;
	}

} );
