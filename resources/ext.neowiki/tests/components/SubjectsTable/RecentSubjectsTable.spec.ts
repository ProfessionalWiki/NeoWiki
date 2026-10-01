import { flushPromises, mount, VueWrapper } from '@vue/test-utils';
import { beforeEach, describe, expect, it, vi } from 'vitest';
import RecentSubjectsTable from '@/components/SubjectsTable/RecentSubjectsTable.vue';
import { Service } from '@/NeoWikiServices.ts';
import { createI18nMock, setupMwMock } from '../../VueTestHelpers.ts';

let getSubjectSummaries: ReturnType<typeof vi.fn>;

function mountTable(): VueWrapper {
	setupMwMock( { functions: [ 'config', 'msg', 'message', 'util' ] } );

	return mount( RecentSubjectsTable, {
		global: {
			mocks: { $i18n: createI18nMock() },
			provide: { [ Service.SubjectSummaryLookup ]: { getSubjectSummaries } },
		},
	} );
}

describe( 'RecentSubjectsTable', () => {

	beforeEach( () => {
		getSubjectSummaries = vi.fn().mockResolvedValue( {
			subjects: [ {
				id: 's1demo1aaaaaaa1', displayName: 'Commodore 64', displayNameIsGenerated: false, schema: 'Computer',
				pageId: 1, pageTitle: 'Home computers', lastEdited: '2026-10-01T14:02:00Z',
			} ],
			nextCursor: 'more',
		} );
	} );

	it( 'asks for the five newest Subjects', async () => {
		mountTable();
		await flushPromises();

		expect( getSubjectSummaries ).toHaveBeenCalledWith( {
			schema: null, search: '', sort: 'newest', direction: 'desc', cursor: null, limit: 5,
		} );
	} );

	it( 'shows them without sortable columns', async () => {
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'Commodore 64' );
		expect( wrapper.find( 'thead button' ).exists() ).toBe( false );
	} );

	it( 'links to the full list below the rows', async () => {
		const wrapper = mountTable();
		await flushPromises();

		const link = wrapper.find( 'a[href="/wiki/Special:Subjects"]' );

		expect( link.text() ).toBe( 'neowiki-subjects-view-all' );
		expect( link.element.closest( '.cdx-table__footer' ) ).not.toBeNull();
	} );

	it( 'says the wiki has no Subjects yet when there are none', async () => {
		getSubjectSummaries.mockResolvedValue( { subjects: [], nextCursor: null } );
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-subjects-empty' );
	} );

	it( 'says nothing about an empty list while it loads', async () => {
		getSubjectSummaries.mockReturnValue( new Promise( () => {
			// Never lands.
		} ) );
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).not.toContain( 'neowiki-subjects-empty' );
	} );

	it( 'says the list could not be loaded when the request fails', async () => {
		getSubjectSummaries.mockRejectedValue( new Error( 'boom' ) );
		const wrapper = mountTable();
		await flushPromises();

		expect( wrapper.text() ).toContain( 'neowiki-subjects-load-error' );
	} );

} );
