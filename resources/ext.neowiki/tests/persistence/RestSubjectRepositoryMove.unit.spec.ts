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

function respondingLike( status: number, body: unknown ): Partial<HttpClient> {
	return {
		post: vi.fn().mockResolvedValue( new Response( typeof body === 'string' ? body : JSON.stringify( body ), { status } ) ),
	};
}

describe( 'RestSubjectRepository.moveSubject', () => {

	it( 'posts the target page and the promotion choice', async () => {
		const post = vi.fn().mockResolvedValue( { ok: true } as Response );

		await newRepository( { post } ).moveSubject( SUBJECT_ID, 12, true, 'Filed properly' );

		expect( post.mock.calls[ 0 ][ 0 ] ).toBe( MOVE_URL );
		expect( post.mock.calls[ 0 ][ 1 ] ).toEqual( { targetPageId: 12, makeMainSubject: true, comment: 'Filed properly' } );
	} );

	it.each( [
		[ 403, 'You do not have the necessary permissions to move this subject' ],
		[ 409, 'Subject is already on the target page' ],
		[ 500, 'The graph store is unreachable' ],
	] )( 'carries the server\'s own reason for a %i, rather than the status code', async ( status, message ) => {
		const repository = newRepository( respondingLike( status, { status: 'error', message } ) );

		await expect( repository.moveSubject( SUBJECT_ID, 12, false ) ).rejects.toThrow( message );
	} );

	it( 'falls back to a generic message when a failed move gives none', async () => {
		const repository = newRepository( respondingLike( 500, '<html>Internal error</html>' ) );

		await expect( repository.moveSubject( SUBJECT_ID, 12, false ) )
			.rejects.toThrow( 'Error moving subject' );
	} );

	it( 'resolves when the move lands', async () => {
		const post = vi.fn().mockResolvedValue( { ok: true } as Response );

		await expect( newRepository( { post } ).moveSubject( SUBJECT_ID, 12, false ) ).resolves.toBeUndefined();
	} );

} );
