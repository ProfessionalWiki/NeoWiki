import { describe, expect, it } from 'vitest';
import { RestSubjectCountLookup } from '@/persistence/RestSubjectCountLookup';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';

const COUNTS_URL = '/rest.php/neowiki/v0/subject-counts';

function newLookup( response: Response ): RestSubjectCountLookup {
	return new RestSubjectCountLookup( '/rest.php', new InMemoryHttpClient( { [ COUNTS_URL ]: response } ) );
}

describe( 'RestSubjectCountLookup', () => {

	it( 'reads the count of each Schema', async () => {
		const lookup = newLookup( { ok: true, json: async () => ( { counts: { Artist: 12, Artwork: 30 } } ) } as Response );

		expect( await lookup.getSubjectCounts() ).toEqual( new Map( [ [ 'Artist', 12 ], [ 'Artwork', 30 ] ] ) );
	} );

	it( 'throws when the endpoint fails', async () => {
		const lookup = newLookup( { ok: false, json: async () => ( {} ) } as Response );

		await expect( lookup.getSubjectCounts() ).rejects.toThrow( 'Error fetching subject counts' );
	} );

} );
