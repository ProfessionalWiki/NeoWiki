import { beforeEach, describe, expect, it } from 'vitest';
import { setupMwMock } from '../VueTestHelpers.ts';
import {
	describeLastEdited, emptyStateMessage, schemaMenuNames, subjectColumns, summaryName, summaryOrder, summaryUrl,
} from '@/presentation/subjectSummaryTable.ts';
import type { SubjectSummary } from '@/application/SubjectSummaryLookup.ts';

const SUMMARY: SubjectSummary = {
	id: 's1demo1aaaaaaa1', displayName: 'ZX Spectrum 48K', displayNameIsGenerated: false, schema: 'Computer',
	pageId: 7, pageTitle: 'Sinclair ZX Spectrum', lastEdited: '2026-10-01T14:02:00Z',
};

const NOW = new Date( '2026-10-01T14:02:00Z' );

// In the locale the tests run in, as the interface words it.
function inWords( value: number, unit: Intl.RelativeTimeFormatUnit ): string {
	return new Intl.RelativeTimeFormat( undefined, { numeric: 'auto' } ).format( value, unit );
}

function onWiki( subjectFirst: boolean ): void {
	setupMwMock( {
		functions: [ 'config', 'msg', 'util' ],
		config: { wgNeoWikiSubjectFirst: subjectFirst },
	} );
}

describe( 'subjectSummaryTable', () => {

	beforeEach( () => onWiki( false ) );

	it( 'lists name and ID first, Schema and page when asked, and the edit last', () => {
		expect( subjectColumns( { showSchema: true, showPage: true, sortable: true } ).map( ( c ) => c.id ) )
			.toEqual( [ 'name', 'id', 'schema', 'page', 'edited' ] );
		expect( subjectColumns( { showSchema: false, showPage: false, sortable: true } ).map( ( c ) => c.id ) )
			.toEqual( [ 'name', 'id', 'edited' ] );
	} );

	it( 'never lets the ID column sort', () => {
		const columns = subjectColumns( { showSchema: true, showPage: true, sortable: true } );

		expect( columns.filter( ( c ) => c.allowSort ).map( ( c ) => c.id ) ).toEqual( [ 'name', 'schema', 'page', 'edited' ] );
	} );

	it( 'lets nothing sort when the table does not', () => {
		expect( subjectColumns( { showSchema: true, showPage: true, sortable: false } ).some( ( c ) => c.allowSort ) )
			.toBe( false );
	} );

	it( 'orders newest first when no column is sorted', () => {
		expect( summaryOrder( {} ) ).toEqual( { sort: 'newest', direction: 'desc' } );
		expect( summaryOrder( { name: 'none' } ) ).toEqual( { sort: 'newest', direction: 'desc' } );
	} );

	it( 'orders by the sorted column', () => {
		expect( summaryOrder( { edited: 'asc' } ) ).toEqual( { sort: 'edited', direction: 'asc' } );
		expect( summaryOrder( { name: 'desc' } ) ).toEqual( { sort: 'name', direction: 'desc' } );
	} );

	it( 'names an unnamed Subject by its bracketed id', () => {
		expect( summaryName( { ...SUMMARY, displayName: 'Computer', displayNameIsGenerated: true } ) )
			.toBe( '(s1demo1aaaaaaa1)' );
	} );

	it( 'names a named Subject by its name', () => {
		expect( summaryName( SUMMARY ) ).toBe( 'ZX Spectrum 48K' );
	} );

	it( 'links to the Subject on a subject-first wiki', () => {
		onWiki( true );

		expect( summaryUrl( SUMMARY ) ).toBe( '/wiki/Special:Subject/s1demo1aaaaaaa1' );
	} );

	it( 'links to the Subject\'s row on its page\'s Data tab on a page-first wiki', () => {
		expect( summaryUrl( SUMMARY ) ).toBe( '/wiki/Sinclair ZX Spectrum?action=subjects#s1demo1aaaaaaa1' );
	} );

	it( 'says how long before now the page was last edited', () => {
		expect( describeLastEdited( '2026-10-01T11:02:00Z', NOW )?.relative ).toBe( inWords( -3, 'hour' ) );
	} );

	it( 'counts the time since the edit in the largest whole unit', () => {
		expect( describeLastEdited( '2026-09-14T14:02:00Z', NOW )?.relative ).toBe( inWords( -2, 'week' ) );
	} );

	it( 'reads an edit dated after now as now', () => {
		expect( describeLastEdited( '2026-10-01T14:03:00Z', NOW )?.relative ).toBe( inWords( 0, 'second' ) );
	} );

	it( 'gives the full time of the edit', () => {
		expect( describeLastEdited( '2026-10-01T11:02:00Z', NOW )?.full ).toContain( '2026' );
	} );

	it( 'describes no edit time that does not parse', () => {
		expect( describeLastEdited( 'not a time', NOW ) ).toBeNull();
	} );

	it( 'puts the selected Schema among the menu\'s names, in order, when the Schema list lacks it', () => {
		expect( schemaMenuNames( [ 'Zebra', 'Ant' ], 'Mouse' ) ).toEqual( [ 'Ant', 'Mouse', 'Zebra' ] );
	} );

	it( 'lists a selected Schema the Schema list has only once', () => {
		expect( schemaMenuNames( [ 'Zebra', 'Ant' ], 'Zebra' ) ).toEqual( [ 'Ant', 'Zebra' ] );
	} );

	it( 'says no Subject matches a search', () => {
		expect( emptyStateMessage( 'zx', 'Computer' ) ).toBe( 'neowiki-subjects-no-matchzx' );
	} );

	it( 'says a Schema has no Subjects yet', () => {
		expect( emptyStateMessage( '', 'Computer' ) ).toBe( 'neowiki-subjects-empty-schemaComputer' );
	} );

	it( 'says the wiki has no Subjects yet', () => {
		expect( emptyStateMessage( '', null ) ).toBe( 'neowiki-subjects-empty' );
	} );

} );
