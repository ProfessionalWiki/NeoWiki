import { describe, expect, it, vi } from 'vitest';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository';
import { SubjectId } from '@/domain/SubjectId';
import type { SubjectDeserializer } from '@/persistence/SubjectDeserializer';
import type { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

const REST_URL = '/rest.php';
const SUBJECT_ID = new SubjectId( 's11111111111maa' );
const MOVE_URL = `${ REST_URL }/neowiki/v0/subject/s11111111111maa/move`;

function newRepository( httpClient: Partial<HttpClient> ): RestSubjectRepository {
	// A move's response carries no Subject and no Schema, so neither deserializer is ever reached.
	return new RestSubjectRepository(
		REST_URL,
		httpClient as HttpClient,
		{} as SubjectDeserializer,
		{} as SchemaDeserializer,
	);
}

/**
 * What the production client does with a refused move: it accepts only 2xx and 422, so every other
 * status arrives as a rejection carrying the parsed body, never as a Response.
 */
function rejectingLike( status: number, body: unknown ): Partial<HttpClient> {
	return {
		post: vi.fn().mockRejectedValue(
			Object.assign( new Error( `Request failed with status code ${ status }` ), {
				response: { status, data: body },
			} ),
		),
	};
}

describe( 'RestSubjectRepository.moveSubject', () => {

	it( 'posts the target page and the promotion choice', async () => {
		const post = vi.fn().mockResolvedValue( { ok: true } as Response );

		await newRepository( { post } ).moveSubject( SUBJECT_ID, 12, true, 'Filed properly' );

		expect( post ).toHaveBeenCalledWith(
			MOVE_URL,
			{ targetPageId: 12, makeMainSubject: true, comment: 'Filed properly' },
			{ headers: { 'Content-Type': 'application/json' } },
		);
	} );

	it( 'carries the server\'s own reason through a rejection', async () => {
		const repository = newRepository(
			rejectingLike( 409, { status: 'error', message: 'Subject is already on the target page' } ),
		);

		await expect( repository.moveSubject( SUBJECT_ID, 12, false ) )
			.rejects.toThrow( 'Subject is already on the target page' );
	} );

	it( 'carries a refusal\'s reason through, rather than the status code', async () => {
		const repository = newRepository(
			rejectingLike( 403, { status: 'error', message: 'You do not have the necessary permissions to move this subject' } ),
		);

		await expect( repository.moveSubject( SUBJECT_ID, 12, false ) )
			.rejects.toThrow( 'You do not have the necessary permissions to move this subject' );
	} );

	it( 'falls back to a generic message when the rejection carries none', async () => {
		const repository = newRepository( { post: vi.fn().mockRejectedValue( new Error( 'Network down' ) ) } );

		await expect( repository.moveSubject( SUBJECT_ID, 12, false ) )
			.rejects.toThrow( 'Error moving subject' );
	} );

	it( 'reads the message off a non-ok response too, for clients that do not reject', async () => {
		const post = vi.fn().mockResolvedValue( {
			ok: false,
			json: async () => ( { status: 'error', message: 'Target page not found' } ),
		} as unknown as Response );

		await expect( newRepository( { post } ).moveSubject( SUBJECT_ID, 12, false ) )
			.rejects.toThrow( 'Target page not found' );
	} );

	it( 'resolves when the move lands', async () => {
		const post = vi.fn().mockResolvedValue( { ok: true } as Response );

		await expect( newRepository( { post } ).moveSubject( SUBJECT_ID, 12, false ) ).resolves.toBeUndefined();
	} );

} );
