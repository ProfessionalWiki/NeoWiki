import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
	subjectExportUrls, pageExportUrls, projectionLabel,
} from '@/presentation/DataExportMenu';

describe( 'DataExportMenu', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			util: { wikiScript: vi.fn( () => '/w/rest.php' ) },
			msg: vi.fn( ( key: string, ...params: string[] ) =>
				params.length > 0 ? `[${ key }|${ params.join( ',' ) }]` : `[${ key }]` ),
		} );
	} );

	describe( 'subjectExportUrls', () => {
		it( 'builds the JSON URL at the subject base', () => {
			expect( subjectExportUrls( 's2picasso2aaaa2' ).jsonUrl )
				.toBe( '/w/rest.php/neowiki/v0/subject/s2picasso2aaaa2' );
		} );

		it( 'builds RDF URLs with projection and format, percent-encoding the projection', () => {
			const { rdfUrl } = subjectExportUrls( 's1aaaaaaaaaaaa1' );
			expect( rdfUrl( 'native', 'turtle' ) )
				.toBe( '/w/rest.php/neowiki/v0/subject/s1aaaaaaaaaaaa1/rdf?projection=native&format=turtle' );
			expect( rdfUrl( 'Wikidata items', 'trig' ) )
				.toBe( '/w/rest.php/neowiki/v0/subject/s1aaaaaaaaaaaa1/rdf?projection=Wikidata%20items&format=trig' );
		} );
	} );

	describe( 'pageExportUrls', () => {
		it( 'targets the page subjects JSON and the page RDF endpoint', () => {
			const { jsonUrl, rdfUrl } = pageExportUrls( 42 );
			expect( jsonUrl ).toBe( '/w/rest.php/neowiki/v0/page/42/subjects' );
			expect( rdfUrl( 'EDM', 'turtle' ) )
				.toBe( '/w/rest.php/neowiki/v0/page/42/rdf?projection=EDM&format=turtle' );
		} );
	} );

	describe( 'projectionLabel', () => {
		it( 'maps native to the Native message and passes other names through', () => {
			expect( projectionLabel( 'native' ) ).toBe( '[neowiki-managesubjects-export-native]' );
			expect( projectionLabel( 'EDM' ) ).toBe( 'EDM' );
		} );
	} );
} );
