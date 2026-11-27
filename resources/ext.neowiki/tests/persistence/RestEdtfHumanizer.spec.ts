import { describe, expect, it } from 'vitest';
import { RestEdtfHumanizer } from '@/persistence/RestEdtfHumanizer';
import { InMemoryHttpClient } from '@/infrastructure/HttpClient/InMemoryHttpClient';

const REST_URL = '/rest.php';

function newHumanizer( responses: Record<string, Response> ): RestEdtfHumanizer {
	return new RestEdtfHumanizer( REST_URL, new InMemoryHttpClient( responses ) );
}

function jsonResponse( body: unknown ): Response {
	return { ok: true, json: async () => body } as Response;
}

describe( 'RestEdtfHumanizer', () => {

	it( 'returns the humanized reading for a value', async () => {
		const humanizer = newHumanizer( {
			[ `${ REST_URL }/neowiki/v0/edtf/humanize?value=1985%3F&language=en` ]:
				jsonResponse( { value: '1985?', humanized: '1985 (uncertain)' } ),
		} );

		expect( await humanizer.humanize( '1985?', 'en' ) ).toBe( '1985 (uncertain)' );
	} );

	it( 'returns null when the library provides no reading', async () => {
		const humanizer = newHumanizer( {
			[ `${ REST_URL }/neowiki/v0/edtf/humanize?value=not-a-date&language=en` ]:
				jsonResponse( { value: 'not-a-date', humanized: null } ),
		} );

		expect( await humanizer.humanize( 'not-a-date', 'en' ) ).toBeNull();
	} );

	it( 'throws when the request fails', async () => {
		const humanizer = newHumanizer( {
			[ `${ REST_URL }/neowiki/v0/edtf/humanize?value=1985%3F&language=en` ]:
				{ ok: false, json: async () => ( {} ) } as Response,
		} );

		await expect( humanizer.humanize( '1985?', 'en' ) )
			.rejects.toThrow( 'Error humanizing EDTF value' );
	} );

} );
