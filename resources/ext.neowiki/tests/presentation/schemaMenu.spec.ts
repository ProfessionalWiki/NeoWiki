import { beforeEach, describe, expect, it } from 'vitest';
import { schemasMatchingSearch } from '@/presentation/schemaMenu';
import { schemaSummaryOf, type SchemaSummary } from '@/application/SchemaLookup';
import { newSchema } from '@/TestHelpers';
import { setupMwMock } from '../VueTestHelpers.ts';

function summary( name: string, labels: Record<string, string> ): SchemaSummary {
	return schemaSummaryOf( newSchema( { title: name, labels: labels } ) );
}

function namesFound( summaries: SchemaSummary[], search: string ): string[] {
	return schemasMatchingSearch( summaries, search ).map( ( found ) => found.name );
}

describe( 'schemasMatchingSearch', () => {

	beforeEach( () => {
		setupMwMock( { config: { wgUserLanguage: 'en', wgContentLanguage: 'en' } } );
	} );

	it( 'lists a Schema found by the label the reader sees before one found only by a label in another language', () => {
		const summaries = [ summary( 'Exhibit', { de: 'Museumsstück' } ), summary( 'Department', { en: 'Museum wing' } ) ];

		expect( namesFound( summaries, 'mus' ) ).toEqual( [ 'Department', 'Exhibit' ] );
	} );

	it( 'lists a Schema found by its name before one found only by a label in another language', () => {
		const summaries = [ summary( 'Artwork', { de: 'Kunstwerk' } ), summary( 'Kunsthalle', { en: 'Gallery' } ) ];

		expect( namesFound( summaries, 'kunst' ) ).toEqual( [ 'Kunsthalle', 'Artwork' ] );
	} );

} );
