import { describe, it, expect, beforeEach, vi } from 'vitest';
import {
	subjectExportUrls, pageExportUrls, rdfProjectionsFor, rdfMenuItems,
} from '@/presentation/DataExportMenu';

const MESSAGES: Record<string, ( ...params: string[] ) => string> = {
	'neowiki-managesubjects-export-native': () => 'Native',
	'neowiki-managesubjects-export-format-turtle': () => 'Turtle',
	'neowiki-managesubjects-export-format-trig': () => 'TriG',
	'neowiki-managesubjects-export-projection-format': ( projection, format ) => `${ projection } · ${ format }`,
};

describe( 'DataExportMenu', () => {
	beforeEach( () => {
		vi.stubGlobal( 'mw', {
			util: { wikiScript: vi.fn( () => '/w/rest.php' ) },
			msg: vi.fn( ( key: string, ...params: string[] ) => MESSAGES[ key ]( ...params ) ),
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

	describe( 'rdfProjectionsFor', () => {
		const MAPPINGS = [
			{ name: 'EDM', schemas: [ 'Artwork', 'Person' ] },
			{ name: 'CIDOC-CRM', schemas: [ 'Museum' ] },
			{ name: 'Linked Art', schemas: [ 'Artwork', 'Place' ] },
		];

		it( 'offers only native when no Mapping maps the Schemas', () => {
			expect( rdfProjectionsFor( [ 'Attendance' ], MAPPINGS ) ).toEqual( [ 'native' ] );
		} );

		it( 'offers native and the Mapping that maps the Schema', () => {
			expect( rdfProjectionsFor( [ 'Museum' ], MAPPINGS ) ).toEqual( [ 'native', 'CIDOC-CRM' ] );
		} );

		it( 'offers every Mapping that maps one of the Schemas, in the order the Mappings come in', () => {
			expect( rdfProjectionsFor( [ 'Place', 'Person' ], MAPPINGS ) ).toEqual( [ 'native', 'EDM', 'Linked Art' ] );
		} );
	} );

	describe( 'rdfMenuItems', () => {
		const rdfUrl = ( projection: string, format: string ): string => `RDF:${ projection }:${ format }`;

		it( 'names the formats alone when native is the only projection', () => {
			expect( rdfMenuItems( [ 'native' ], rdfUrl ) ).toEqual( [
				{ value: 'RDF:native:turtle', label: 'Turtle' },
				{ value: 'RDF:native:trig', label: 'TriG' },
			] );
		} );

		it( 'names the projection of each format when there are several projections', () => {
			expect( rdfMenuItems( [ 'native', 'EDM' ], rdfUrl ) ).toEqual( [
				{ value: 'RDF:native:turtle', label: 'Native · Turtle' },
				{ value: 'RDF:native:trig', label: 'Native · TriG' },
				{ value: 'RDF:EDM:turtle', label: 'EDM · Turtle' },
				{ value: 'RDF:EDM:trig', label: 'EDM · TriG' },
			] );
		} );
	} );
} );
