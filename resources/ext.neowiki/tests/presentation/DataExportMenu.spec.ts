import { describe, it, expect, beforeEach, vi } from 'vitest';
import { subjectExportMenuItems, pageExportMenuItems } from '@/presentation/DataExportMenu';

describe( 'DataExportMenu', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			util: { wikiScript: vi.fn( () => '/w/rest.php' ) },
			msg: vi.fn( ( key: string, ...params: string[] ) =>
				params.length > 0 ? `[${ key }|${ params.join( ',' ) }]` : `[${ key }]` ),
		} );
	} );

	describe( 'subjectExportMenuItems', () => {
		it( 'lists JSON first, then Turtle and TriG for each projection in order', () => {
			const items = subjectExportMenuItems( 's2picasso2aaaa2', [ 'native', 'EDM' ] );

			expect( items.map( ( item ) => item.value ) ).toEqual( [
				'/w/rest.php/neowiki/v0/subject/s2picasso2aaaa2',
				'/w/rest.php/neowiki/v0/subject/s2picasso2aaaa2/rdf?projection=native&format=turtle',
				'/w/rest.php/neowiki/v0/subject/s2picasso2aaaa2/rdf?projection=native&format=trig',
				'/w/rest.php/neowiki/v0/subject/s2picasso2aaaa2/rdf?projection=EDM&format=turtle',
				'/w/rest.php/neowiki/v0/subject/s2picasso2aaaa2/rdf?projection=EDM&format=trig',
			] );
		} );

		it( 'labels JSON, the native projection, and mapping projections distinctly', () => {
			const items = subjectExportMenuItems( 's2picasso2aaaa2', [ 'native', 'EDM' ] );

			expect( items.map( ( item ) => item.label ) ).toEqual( [
				'[neowiki-managesubjects-export-json]',
				'[neowiki-managesubjects-export-turtle|[neowiki-managesubjects-export-native]]',
				'[neowiki-managesubjects-export-trig|[neowiki-managesubjects-export-native]]',
				'[neowiki-managesubjects-export-turtle|EDM]',
				'[neowiki-managesubjects-export-trig|EDM]',
			] );
		} );

		it( 'percent-encodes the projection name in the RDF URLs', () => {
			const items = subjectExportMenuItems( 's1aaaaaaaaaaaa1', [ 'Wikidata items' ] );

			expect( items[ 1 ].value ).toBe(
				'/w/rest.php/neowiki/v0/subject/s1aaaaaaaaaaaa1/rdf?projection=Wikidata%20items&format=turtle',
			);
		} );

		it( 'returns only the JSON entry when there are no readable projections', () => {
			const items = subjectExportMenuItems( 's1aaaaaaaaaaaa1', [] );

			expect( items ).toHaveLength( 1 );
			expect( items[ 0 ].value ).toBe( '/w/rest.php/neowiki/v0/subject/s1aaaaaaaaaaaa1' );
		} );
	} );

	describe( 'pageExportMenuItems', () => {
		it( 'targets the page subjects JSON and the page RDF endpoint', () => {
			const items = pageExportMenuItems( 42, [ 'native', 'EDM' ] );

			expect( items.map( ( item ) => item.value ) ).toEqual( [
				'/w/rest.php/neowiki/v0/page/42/subjects',
				'/w/rest.php/neowiki/v0/page/42/rdf?projection=native&format=turtle',
				'/w/rest.php/neowiki/v0/page/42/rdf?projection=native&format=trig',
				'/w/rest.php/neowiki/v0/page/42/rdf?projection=EDM&format=turtle',
				'/w/rest.php/neowiki/v0/page/42/rdf?projection=EDM&format=trig',
			] );
		} );
	} );
} );
