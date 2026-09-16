import { describe, expect, it } from 'vitest';
import { MediaWikiPageSaver } from '@/persistence/MediaWikiPageSaver.ts';

/**
 * mw.Rest hands its callbacks back a jQuery promise, not a native one, so the double stands in
 * for that shape rather than resolving a Promise the production code never awaits.
 */
function restCall( outcome: 'done' | 'fail', payload: unknown ): unknown {
	const call = {
		done( callback: ( response: unknown ) => void ): unknown {
			if ( outcome === 'done' ) {
				callback( payload );
			}
			return call;
		},
		fail( callback: ( code: string, failure: unknown ) => void ): unknown {
			if ( outcome === 'fail' ) {
				callback( 'http', payload );
			}
			return call;
		},
	};

	return call;
}

function restFailure( responseJSON: unknown, exception = 'Bad Request' ): unknown {
	return { xhr: { responseJSON }, textStatus: 'error', exception };
}

function newSaver( putOutcome: 'done' | 'fail', putPayload: unknown, userLanguage = 'en' ): MediaWikiPageSaver {
	const mediaWiki = {
		Api: class {
			public getEditToken(): Promise<string> {
				return Promise.resolve( 'token' );
			}
		},
		Rest: class {
			public get(): Promise<unknown> {
				return Promise.resolve( { latest: { id: 7 } } );
			}

			public put(): unknown {
				return restCall( putOutcome, putPayload );
			}
		},
		config: {
			get: ( key: string ): string | null => key === 'wgUserLanguage' ? userLanguage : null,
		},
	};

	return new MediaWikiPageSaver( mediaWiki as unknown as typeof mw );
}

function save( saver: MediaWikiPageSaver ): Promise<{ success: boolean; message?: string }> {
	return saver.savePage( 'Schema:Product', '{}', 'Comment', 'NeoWikiSchema' );
}

describe( 'MediaWikiPageSaver', () => {

	it( 'reports success when the page is saved', async () => {
		const status = await save( newSaver( 'done', {} ) );

		expect( status.success ).toBe( true );
	} );

	it( 'reports a failure as a status rather than a rejection', async () => {
		const failure = restFailure( { errorKey: 'neowiki-schema-invalid' } );

		const status = await save( newSaver( 'fail', failure ) );

		expect( status.success ).toBe( false );
	} );

	it( 'reports the message the REST error carries', async () => {
		const failure = restFailure( {
			errorKey: 'neowiki-schema-invalid',
			messageTranslations: { en: 'Schema content is invalid (1 error):' },
		} );

		const status = await save( newSaver( 'fail', failure ) );

		expect( status.message ).toBe( 'Schema content is invalid (1 error):' );
	} );

	it( 'reports the message in the language the reader is using', async () => {
		const failure = restFailure( {
			messageTranslations: { en: 'Schema content is invalid', de: 'Schemainhalt ist ungültig' },
		} );

		const status = await save( newSaver( 'fail', failure, 'de' ) );

		expect( status.message ).toBe( 'Schemainhalt ist ungültig' );
	} );

	it( 'falls back to a translation the response does carry', async () => {
		const failure = restFailure( {
			messageTranslations: { en: 'Schema content is invalid' },
		} );

		const status = await save( newSaver( 'fail', failure, 'de' ) );

		expect( status.message ).toBe( 'Schema content is invalid' );
	} );

	it( 'falls back to the error key when the response carries no message', async () => {
		const status = await save( newSaver( 'fail', restFailure( { errorKey: 'rest-update-cannot-create-page' } ) ) );

		expect( status.message ).toBe( 'rest-update-cannot-create-page' );
	} );

	it( 'falls back to the HTTP reason when the response carries no error body', async () => {
		const status = await save( newSaver( 'fail', restFailure( undefined, 'Conflict' ) ) );

		expect( status.message ).toBe( 'Conflict' );
	} );

} );
