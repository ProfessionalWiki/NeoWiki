import { mount, VueWrapper } from '@vue/test-utils';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';
import SubjectSummaryCell from '@/components/SubjectsTable/SubjectSummaryCell.vue';
import type { SubjectSummary } from '@/application/SubjectSummaryLookup.ts';
import type { SubjectColumnId } from '@/presentation/subjectSummaryTable.ts';
import { setupMwMock } from '../../VueTestHelpers.ts';

const SUMMARY: SubjectSummary = {
	id: 's1demo1aaaaaaa1', displayName: 'ZX Spectrum 48K', displayNameIsGenerated: false, schema: 'Computer',
	pageId: 7, pageTitle: 'Sinclair ZX Spectrum', lastEdited: '2026-10-01T14:02:00Z',
};

function mountCell( column: SubjectColumnId, overrides: Partial<SubjectSummary> = {} ): VueWrapper {
	return mount( SubjectSummaryCell, { props: { column, summary: { ...SUMMARY, ...overrides } } } );
}

describe( 'SubjectSummaryCell', () => {

	beforeEach( () => {
		setupMwMock( { functions: [ 'config', 'msg', 'message', 'util' ], config: { wgNeoWikiSubjectFirst: false } } );
		vi.useFakeTimers();
		vi.setSystemTime( new Date( '2026-10-01T17:02:00Z' ) );
	} );

	afterEach( () => {
		vi.useRealTimers();
	} );

	it( 'links the name to the Subject\'s row on its page', () => {
		const link = mountCell( 'name' ).find( 'a' );

		expect( link.text() ).toBe( 'ZX Spectrum 48K' );
		expect( link.attributes( 'href' ) ).toBe( '/wiki/Sinclair ZX Spectrum?action=subjects#s1demo1aaaaaaa1' );
	} );

	it( 'shows the Subject id', () => {
		expect( mountCell( 'id' ).text() ).toBe( 's1demo1aaaaaaa1' );
	} );

	it( 'names the Schema', () => {
		expect( mountCell( 'schema' ).find( '.ext-neowiki-schema-name__text' ).text() ).toBe( 'Computer' );
	} );

	it( 'links the page the Subject is on', () => {
		const link = mountCell( 'page' ).find( 'a' );

		expect( link.text() ).toBe( 'Sinclair ZX Spectrum' );
		expect( link.attributes( 'href' ) ).toBe( '/wiki/Sinclair ZX Spectrum' );
	} );

	it( 'shows how long ago the page was edited in a time element', () => {
		const time = mountCell( 'edited' ).find( 'time' );

		expect( time.attributes( 'datetime' ) ).toBe( '2026-10-01T14:02:00Z' );
		expect( time.text() ).toBe( new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } ).format( -3, 'hour' ) );
	} );

	it( 'gives the full edit time as the title', () => {
		expect( mountCell( 'edited' ).find( 'time' ).attributes( 'title' ) ).toContain( '2026' );
	} );

	it( 'shows an edit time that does not parse as it came', () => {
		const wrapper = mountCell( 'edited', { lastEdited: 'not a time' } );

		expect( wrapper.find( 'time' ).exists() ).toBe( false );
		expect( wrapper.text() ).toBe( 'not a time' );
	} );

} );
