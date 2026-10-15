import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RecentSubjectsTable from '@/components/SubjectsTable/RecentSubjectsTable.vue';
import { Service } from '@/NeoWikiServices.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

let getRecentlyEditedSubjects: ReturnType<typeof vi.fn>;

function mountTable(): VueWrapper {
	return mount( RecentSubjectsTable, {
		global: {
			mocks: { $i18n: createI18nMock() },
			provide: { [ Service.SubjectSummaryLookup ]: { getRecentlyEditedSubjects } },
		},
	} );
}

describe( 'RecentSubjectsTable', () => {

	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'msg', 'message', 'util' ] } );
		getRecentlyEditedSubjects = vi.fn().mockResolvedValue( [ {
			id: 's1demo1aaaaaaa1', displayName: 'Commodore 64', displayNameIsGenerated: false, hostSubject: null,
			schema: 'Computer',
			pageId: 1, pageTitle: 'Home computers', lastEdited: '2026-10-01T14:02:00Z',
		} ] );
	} );

	it( 'is captioned as the recently edited Subjects', async () => {
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-subjects-recently-edited' );
	} );

	it( 'asks for the five Subjects edited last', async () => {
		mountTable();
		await flushPromises();

		expect( getRecentlyEditedSubjects ).toHaveBeenCalledWith( 5 );
	} );

	it( 'shows them without sortable columns', async () => {
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'Commodore 64' );
		expect( wrapper.find( 'thead button' ).exists() ).toBe( false );
	} );

	it( 'leaves the Subject IDs out', async () => {
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).not.toContain( 's1demo1aaaaaaa1' );
	} );

	it( 'links to the full list below the rows', async () => {
		const wrapper = mountTable();
		await flushPromises();

		const link = wrapper.find( 'a[href="/wiki/Special:Subjects"]' );

		expect( link.text() ).toBe( 'neowiki-subjects-view-all' );
		expect( link.element.closest( '.cdx-table__footer' ) ).not.toBeNull();
	} );

	it( 'says the wiki has no Subjects yet when there are none', async () => {
		getRecentlyEditedSubjects.mockResolvedValue( [] );
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-subjects-empty' );
	} );

	it( 'says nothing about an empty list while it loads', async () => {
		getRecentlyEditedSubjects.mockReturnValue( new Promise( () => {
			// Never lands.
		} ) );
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).not.toContain( 'neowiki-subjects-empty' );
	} );

	it( 'says the list could not be loaded when the request fails', async () => {
		getRecentlyEditedSubjects.mockRejectedValue( new Error( 'boom' ) );
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-subjects-load-error' );
	} );

} );
