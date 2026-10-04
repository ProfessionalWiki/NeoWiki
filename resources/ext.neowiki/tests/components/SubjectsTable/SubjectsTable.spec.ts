import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import { createPinia, setActivePinia } from 'pinia';
import SubjectsTable from '@/components/SubjectsTable/SubjectsTable.vue';
import { Service } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { createI18nMock, findNextPageButton, setupMwMock } from '../../VueTestHelpers.ts';
import type { SubjectSummaries, SubjectSummary, SubjectSummaryQuery } from '@/application/SubjectSummaryLookup.ts';

function summary( id: string, overrides: Partial<SubjectSummary> = {} ): SubjectSummary {
	return {
		id, displayName: `Name ${ id }`, displayNameIsGenerated: false, schema: 'Computer', pageId: 1,
		pageTitle: 'Some page', lastEdited: '2026-10-01T14:02:00Z', ...overrides,
	};
}

const FULL_PAGE: SubjectSummaries = {
	subjects: Array.from( { length: 10 }, ( _value, index ) => summary( `s1demo1aaaaa${ 10 + index }` ) ),
	nextCursor: 'page-2',
};

let pinia: ReturnType<typeof createPinia>;
let getSubjectSummaries: ReturnType<typeof vi.fn>;

function mountTable( props: Record<string, unknown> = {}, subjectFirst = false ): VueWrapper {
	setupMwMock( {
		functions: [ 'config', 'msg', 'message', 'util', 'notify' ],
		config: { wgNeoWikiSubjectFirst: subjectFirst },
	} );

	return mount( SubjectsTable, {
		props,
		global: {
			plugins: [ pinia ],
			mocks: { $i18n: createI18nMock() },
			provide: { [ Service.SubjectSummaryLookup ]: { getSubjectSummaries } },
			stubs: { CdxIcon: true },
		},
	} );
}

function lastQuery(): SubjectSummaryQuery {
	return getSubjectSummaries.mock.lastCall![ 0 ];
}

// Scoped to the header: the pager has a CdxSelect of its own, for the page size.
function findSchemaMenu( wrapper: VueWrapper ): VueWrapper {
	return wrapper.find( '.ext-neowiki-subjects-table__controls' ).findComponent( { name: 'CdxSelect' } );
}

async function choosePageSize( wrapper: VueWrapper, size: number ): Promise<void> {
	wrapper.find( '.cdx-table-pager' ).findComponent( { name: 'CdxSelect' } ).vm.$emit( 'update:selected', size );
	await flushPromises();
}

async function goToNextPage( wrapper: VueWrapper ): Promise<void> {
	await findNextPageButton( wrapper ).trigger( 'click' );
	await flushPromises();
}

async function goToPreviousPage( wrapper: VueWrapper ): Promise<void> {
	await wrapper.find( '.cdx-table-pager button[aria-label="Previous page"]' ).trigger( 'click' );
	await flushPromises();
}

async function searchFor( wrapper: VueWrapper, text: string ): Promise<void> {
	await wrapper.find( 'input[type="search"]' ).setValue( text );
	vi.advanceTimersByTime( 300 );
	await flushPromises();
}

function columnLabels( wrapper: VueWrapper ): string[] {
	return wrapper.findAll( 'thead th' ).map( ( th ) => th.text() );
}

function answerEveryPageInFull(): void {
	getSubjectSummaries.mockImplementation( async ( query: SubjectSummaryQuery ) => ( {
		subjects: Array.from( { length: query.limit }, ( _value, index ) => summary( `s1demo1aaaa${ 1000 + index }` ) ),
		nextCursor: 'more',
	} ) );
}

describe( 'SubjectsTable', () => {

	beforeEach( () => {
		vi.useFakeTimers();
		pinia = createPinia();
		setActivePinia( pinia );
		useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue( [
			{ name: 'Person', description: '', propertyCount: 1 },
			{ name: 'Computer', description: '', propertyCount: 4 },
		] );
		getSubjectSummaries = vi.fn().mockResolvedValue( { subjects: [ summary( 's1demo1aaaaaaa1' ) ], nextCursor: null } );
	} );

	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'asks for the newest Subjects of every Schema first', async () => {
		mountTable();
		await flushPromises();

		expect( lastQuery() ).toEqual( {
			schema: null, search: '', sort: 'newest', direction: 'desc', cursor: null, limit: 10,
		} );
	} );

	it( 'starts on the Schema it is given', async () => {
		mountTable( { initialSchema: 'Computer' } );
		await flushPromises();

		expect( lastQuery().schema ).toBe( 'Computer' );
	} );

	it( 'shows the Schema it starts on in the menu when the Schema list lacks it', async () => {
		const wrapper = mountTable( { initialSchema: 'Unlisted' } );
		await flushPromises();

		expect( findSchemaMenu( wrapper ).find( '.cdx-select-vue__handle' ).text() ).toBe( 'Unlisted' );
	} );

	it( 'offers the wiki\'s Schemas in the menu', async () => {
		useSchemaStore().fetchAllSchemaSummaries = vi.fn().mockResolvedValue( [
			{ name: 'Gadget', description: '', propertyCount: 1 },
		] );
		const wrapper = mountTable();
		await flushPromises();

		expect( findSchemaMenu( wrapper ).text() ).toContain( 'Gadget' );
	} );

	it( 'shows an unnamed Subject by its bracketed id', async () => {
		getSubjectSummaries.mockResolvedValue( {
			subjects: [ summary( 's1demo1aaaaaaa1', { displayName: 'Computer', displayNameIsGenerated: true } ) ],
			nextCursor: null,
		} );
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.find( 'tbody a' ).text() ).toBe( '(s1demo1aaaaaaa1)' );
	} );

	it( 'shows the Schema column for every Schema and the Page column on a page-first wiki', async () => {
		const wrapper = mountTable();
		await flushPromises();

		expect( columnLabels( wrapper ) ).toEqual( [
			'neowiki-subjects-column-name', 'neowiki-subjects-column-id', 'neowiki-subjects-column-schema',
			'neowiki-subjects-column-page', 'neowiki-subjects-column-edited',
		] );
	} );

	it( 'leaves the Page column out on a subject-first wiki', async () => {
		const wrapper = mountTable( {}, true );
		await flushPromises();

		expect( columnLabels( wrapper ) ).not.toContain( 'neowiki-subjects-column-page' );
	} );

	it( 'searches once the typing stops', async () => {
		const wrapper = mountTable();
		await flushPromises();
		getSubjectSummaries.mockClear();

		await wrapper.find( 'input[type="search"]' ).setValue( ' zx' );
		await wrapper.find( 'input[type="search"]' ).setValue( ' zx spec ' );
		vi.advanceTimersByTime( 299 );
		await flushPromises();
		expect( getSubjectSummaries ).not.toHaveBeenCalled();

		vi.advanceTimersByTime( 1 );
		await flushPromises();
		expect( getSubjectSummaries ).toHaveBeenCalledOnce();
		expect( lastQuery() ).toMatchObject( { search: 'zx spec', cursor: null } );
	} );

	it( 'asks again for the Schema chosen in the menu and tells its parent', async () => {
		const wrapper = mountTable();
		await flushPromises();

		findSchemaMenu( wrapper ).vm.$emit( 'update:selected', 'Person' );
		await flushPromises();

		expect( lastQuery() ).toMatchObject( { schema: 'Person', cursor: null } );
		expect( wrapper.emitted( 'update:schema' ) ).toEqual( [ [ 'Person' ] ] );
		expect( columnLabels( wrapper ) ).not.toContain( 'neowiki-subjects-column-schema' );
	} );

	it( 'asks again in the order of a sorted column', async () => {
		const wrapper = mountTable();
		await flushPromises();

		await wrapper.find( 'thead th:first-child button' ).trigger( 'click' );
		await flushPromises();

		expect( lastQuery() ).toMatchObject( { sort: 'name', direction: 'asc', cursor: null } );
	} );

	it( 'drops the sort when another Schema is chosen', async () => {
		const wrapper = mountTable();
		await flushPromises();
		await wrapper.find( 'thead th:first-child button' ).trigger( 'click' );
		await flushPromises();
		getSubjectSummaries.mockClear();

		findSchemaMenu( wrapper ).vm.$emit( 'update:selected', 'Person' );
		await flushPromises();

		expect( getSubjectSummaries ).toHaveBeenCalledOnce();
		expect( lastQuery() ).toMatchObject( { schema: 'Person', sort: 'newest', direction: 'desc' } );
	} );

	it( 'returns to the first page when the search changes on a later page', async () => {
		getSubjectSummaries.mockResolvedValue( FULL_PAGE );
		const wrapper = mountTable();
		await flushPromises();
		await goToNextPage( wrapper );
		expect( lastQuery().cursor ).toBe( 'page-2' );

		await searchFor( wrapper, 'zx' );

		expect( lastQuery() ).toMatchObject( { search: 'zx', cursor: null } );
		expect( wrapper.text() ).toContain( '1–10' );
	} );

	it( 'learns where the listing ends from a full page with no next one', async () => {
		getSubjectSummaries.mockResolvedValue( { ...FULL_PAGE, nextCursor: null } );
		const wrapper = mountTable();
		await flushPromises();

		expect( findNextPageButton( wrapper ).attributes( 'disabled' ) ).toBeDefined();
	} );

	it( 'starts again from the first page when asked for rows no page led to', async () => {
		answerEveryPageInFull();
		const wrapper = mountTable();
		await flushPromises();
		await choosePageSize( wrapper, 20 );
		await goToNextPage( wrapper );
		await goToNextPage( wrapper );
		await choosePageSize( wrapper, 10 );

		// Previous moves back from row 41 by the new page size, to row 31, which no page led to.
		await goToPreviousPage( wrapper );

		expect( lastQuery().cursor ).toBeNull();
		expect( wrapper.find( '.cdx-table-pager' ).text() ).toContain( '1–10' );
	} );

	it( 'says nothing about an empty list while the first rows load', async () => {
		getSubjectSummaries.mockReturnValue( new Promise( () => {
			// Never lands.
		} ) );
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).not.toContain( 'neowiki-subjects-empty' );
	} );

	it( 'ignores a response that a newer request overtook', async () => {
		let answerFirst: ( value: SubjectSummaries ) => void = () => undefined;
		getSubjectSummaries
			.mockReturnValueOnce( new Promise( ( resolve ) => {
				answerFirst = resolve;
			} ) )
			.mockResolvedValueOnce( { subjects: [ summary( 's1demo1aaaaaaa2', { displayName: 'Newer' } ) ], nextCursor: null } );
		const wrapper = mountTable();
		await searchFor( wrapper, 'n' );

		answerFirst( { subjects: [ summary( 's1demo1aaaaaaa1', { displayName: 'Older' } ) ], nextCursor: null } );
		await flushPromises();

		expect( wrapper.text() ).toContain( 'Newer' );
		expect( wrapper.text() ).not.toContain( 'Older' );
	} );

	it( 'says the list could not be loaded when the request fails', async () => {
		getSubjectSummaries.mockRejectedValue( new Error( 'boom' ) );
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-subjects-load-error' );
	} );

	it( 'lists only the fixed Schema, with no menu, Schema column or Create button', async () => {
		const wrapper = mountTable( { fixedSchema: 'Computer', canCreate: true } );
		await flushPromises();

		expect( lastQuery().schema ).toBe( 'Computer' );
		expect( findSchemaMenu( wrapper ).exists() ).toBe( false );
		expect( columnLabels( wrapper ) ).not.toContain( 'neowiki-subjects-column-schema' );
		expect( wrapper.find( '.ext-neowiki-subjects-table__create' ).exists() ).toBe( false );
		expect( wrapper.find( '.cdx-table__header__caption' ).text() ).toBe( 'neowiki-special-subjects' );
	} );

	it( 'offers no Create button to a user who cannot create', async () => {
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.find( '.ext-neowiki-subjects-table__create' ).exists() ).toBe( false );
	} );

	it( 'offers to create a Subject of the chosen Schema', async () => {
		const wrapper = mountTable( { initialSchema: 'Computer', canCreate: true } );
		await flushPromises();

		await wrapper.find( '.ext-neowiki-subjects-table__create' ).trigger( 'click' );

		expect( wrapper.emitted( 'create' ) ).toEqual( [ [ 'Computer' ] ] );
	} );

	it( 'offers to create a Subject of any Schema while every Schema is listed', async () => {
		const wrapper = mountTable( { canCreate: true } );
		await flushPromises();

		await wrapper.find( '.ext-neowiki-subjects-table__create' ).trigger( 'click' );

		expect( wrapper.emitted( 'create' ) ).toEqual( [ [ undefined ] ] );
	} );

} );
