import { describe, expect, it } from 'vitest';
import { MediaWikiPageSaver } from '@/persistence/MediaWikiPageSaver.ts';
import { PageSaverStatus, RevisionSaveStatus } from '@/persistence/PageSaver.ts';

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

// What a save made: the page as MediaWiki's REST API hands it back.
const SAVED_PAGE = { latest: { id: 8 }, source: '{\n\t"subjectFirst": true\n}' };

interface SaverDouble {
	editToken?: Promise<string>;
	pageExists?: boolean;
	putBodies?: Record<string, unknown>[];
}

function newSaver(
	putOutcome: 'done' | 'fail',
	putPayload: unknown,
	{ editToken = Promise.resolve( 'token' ), pageExists = true, putBodies = [] }: SaverDouble = {},
): MediaWikiPageSaver {
	const mediaWiki = {
		Api: class {
			public getEditToken(): Promise<string> {
				return editToken;
			}
		},
		Rest: class {
			// mw.Rest rejects the request for a page that does not exist yet, which answers 404.
			public get(): Promise<unknown> {
				return pageExists ? Promise.resolve( { latest: { id: 7 } } ) : Promise.reject( 'http' );
			}

			public put( _path: string, body: Record<string, unknown> ): unknown {
				putBodies.push( body );
				return restCall( putOutcome, putPayload );
			}
		},
	};

	return new MediaWikiPageSaver( mediaWiki as unknown as typeof mw );
}

function save( saver: MediaWikiPageSaver ): Promise<PageSaverStatus> {
	return saver.savePage( 'Schema:Product', '{}', 'Comment', 'NeoWikiSchema' );
}

function saveOver( saver: MediaWikiPageSaver, baseRevisionId: number | null ): Promise<RevisionSaveStatus> {
	return saver.saveRevision( 'MediaWiki:NeoWiki', '{}', 'Comment', 'json', baseRevisionId );
}

// The body of the one REST request a successful save sends.
async function putBodyOf(
	save: ( saver: MediaWikiPageSaver ) => Promise<unknown>,
	{ pageExists = true }: { pageExists?: boolean } = {},
): Promise<Record<string, unknown>> {
	const putBodies: Record<string, unknown>[] = [];

	await save( newSaver( 'done', SAVED_PAGE, { pageExists, putBodies } ) );

	return putBodies[ 0 ];
}

function failureOf( status: PageSaverStatus ): string {
	if ( status.success ) {
		throw new Error( 'Expected a failed save' );
	}

	return status.message;
}

describe( 'MediaWikiPageSaver', () => {

	it( 'reports success when the page is saved', async () => {
		const status = await save( newSaver( 'done', SAVED_PAGE ) );

		expect( status.success ).toBe( true );
	} );

	it( 'reports a refused save as a status rather than a rejection', async () => {
		const status = await save( newSaver( 'fail', restFailure( { errorKey: 'neowiki-schema-invalid' } ) ) );

		expect( status.success ).toBe( false );
	} );

	it( 'reports the message the REST error carries', async () => {
		const failure = restFailure( {
			errorKey: 'neowiki-schema-invalid',
			messageTranslations: { en: 'Schema content is invalid (1 error): /propertyDefinitions/Owner: …' },
		} );

		expect( failureOf( await save( newSaver( 'fail', failure ) ) ) )
			.toBe( 'Schema content is invalid (1 error): /propertyDefinitions/Owner: …' );
	} );

	// MediaWiki answers in the wiki's content language first, so that is the one to show.
	it( 'reports the first translation the response carries', async () => {
		const failure = restFailure( {
			messageTranslations: { de: 'Schemainhalt ist ungültig', en: 'Schema content is invalid' },
		} );

		expect( failureOf( await save( newSaver( 'fail', failure ) ) ) ).toBe( 'Schemainhalt ist ungültig' );
	} );

	it( 'reports the plain message of an error raised outside the localized path', async () => {
		const status = await save( newSaver( 'fail', restFailure( { message: 'Error: exception of type RuntimeException' } ) ) );

		expect( failureOf( status ) ).toBe( 'Error: exception of type RuntimeException' );
	} );

	it( 'falls back to the HTTP reason when the response carries no error body', async () => {
		const status = await save( newSaver( 'fail', restFailure( undefined, 'Conflict' ) ) );

		expect( failureOf( status ) ).toBe( 'Conflict' );
	} );

	// The token is fetched over the network too. Its rejection used to escape as a rejected
	// promise, which is the very thing callers of this cannot see.
	it( 'reports a failure to fetch the edit token as a status too', async () => {
		const status = await save( newSaver( 'done', SAVED_PAGE, { editToken: Promise.reject( new Error( 'Could not get token' ) ) } ) );

		expect( failureOf( status ) ).toBe( 'Could not get token' );
	} );

	it( 'saves a page over its current revision', async () => {
		expect( ( await putBodyOf( save ) ).latest ).toEqual( { id: 7 } );
	} );

	it( 'creates a page that does not exist yet', async () => {
		expect( ( await putBodyOf( save, { pageExists: false } ) ).latest ).toBeUndefined();
	} );

	it( 'saves over the revision it is given', async () => {
		expect( ( await putBodyOf( ( saver ) => saveOver( saver, 5 ) ) ).latest ).toEqual( { id: 5 } );
	} );

	it( 'creates the page when it is given no revision to save over', async () => {
		expect( ( await putBodyOf( ( saver ) => saveOver( saver, null ) ) ).latest ).toBeUndefined();
	} );

	it( 'reports the revision the save made and the page as it saved it', async () => {
		const status = await saveOver( newSaver( 'done', SAVED_PAGE ), 5 );

		expect( status ).toEqual( { success: true, revisionId: 8, source: '{\n\t"subjectFirst": true\n}' } );
	} );

} );
