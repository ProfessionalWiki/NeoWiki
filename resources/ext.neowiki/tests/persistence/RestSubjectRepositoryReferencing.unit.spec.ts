import { describe, expect, it, vi } from 'vitest';
import { RestSubjectRepository } from '@/persistence/RestSubjectRepository';
import { SubjectId } from '@/domain/SubjectId';
import { SubjectWithContext } from '@/domain/SubjectWithContext';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { StatementList } from '@/domain/StatementList';
import type { SubjectDeserializer } from '@/persistence/SubjectDeserializer';
import type { SchemaDeserializer } from '@/persistence/SchemaDeserializer';
import type { HttpClient } from '@/infrastructure/HttpClient/HttpClient';

const REST_URL = '/rest.php';
const SUBJECT_ID = new SubjectId( 's11111111111maa' );
const REFERENCING_URL = `${ REST_URL }/neowiki/v0/subject/s11111111111maa/referencingSubjects?limit=50`;

const ANVIL_JSON = { id: 's11111111111aa1', label: 'Anvil' };
const BELLOWS_JSON = { id: 's11111111111aa2', label: 'Bellows' };

function newSubject( id: string, label: string ): SubjectWithContext {
	return new SubjectWithContext(
		new SubjectId( id ),
		label,
		label,
		false,
		'Product',
		new StatementList( [] ),
		new PageIdentifiers( 7, label ),
	);
}

/**
 * Deserializes the two entries above and rejects anything else, so a test can hand the repository a
 * payload entry it cannot read.
 */
const deserializer = {
	deserialize: ( json: { id: string; label: string } ) => {
		if ( json.id !== ANVIL_JSON.id && json.id !== BELLOWS_JSON.id ) {
			throw new Error( `Cannot deserialize ${ json.id }` );
		}

		return newSubject( json.id, json.label );
	},
} as unknown as SubjectDeserializer;

function newRepository( get: HttpClient['get'] ): RestSubjectRepository {
	return new RestSubjectRepository(
		REST_URL,
		{ get } as HttpClient,
		deserializer,
		{} as SchemaDeserializer,
	);
}

function responding( body: unknown, ok = true ): HttpClient['get'] {
	return vi.fn().mockResolvedValue( { ok, json: () => Promise.resolve( body ) } as Response );
}

describe( 'RestSubjectRepository.getReferencingSubjects', () => {

	it( 'asks the endpoint for this Subject, at the maximum it serves', async () => {
		const get = responding( { referencingSubjects: [], truncated: false } );

		await newRepository( get ).getReferencingSubjects( SUBJECT_ID );

		expect( get ).toHaveBeenCalledWith( REFERENCING_URL );
	} );

	it( 'keeps each Subject with the properties pointing here, in the order served', async () => {
		const result = await newRepository( responding( {
			referencingSubjects: [
				{ subject: BELLOWS_JSON, propertyNames: [ 'Sold in' ] },
				{ subject: ANVIL_JSON, propertyNames: [ 'Made in', 'Sold in' ] },
			],
			truncated: true,
		} ) ).getReferencingSubjects( SUBJECT_ID );

		expect( result.subjects.map( ( each ) => each.subject.getId().text ) )
			.toEqual( [ BELLOWS_JSON.id, ANVIL_JSON.id ] );
		expect( result.subjects.map( ( each ) => each.propertyNames ) )
			.toEqual( [ [ 'Sold in' ], [ 'Made in', 'Sold in' ] ] );
		expect( result.truncated ).toBe( true );
	} );

	// One entry the client cannot read should cost the reader that entry, not the whole list.
	it( 'skips an entry it cannot deserialize', async () => {
		const result = await newRepository( responding( {
			referencingSubjects: [
				{ subject: { id: 'sbrokenbroken1', label: 'Broken' }, propertyNames: [ 'Made in' ] },
				{ subject: ANVIL_JSON, propertyNames: [ 'Made in' ] },
			],
			truncated: false,
		} ) ).getReferencingSubjects( SUBJECT_ID );

		expect( result.subjects.map( ( each ) => each.subject.getId().text ) ).toEqual( [ ANVIL_JSON.id ] );
	} );

	it( 'reports a failed request', async () => {
		await expect( newRepository( responding( {}, false ) ).getReferencingSubjects( SUBJECT_ID ) )
			.rejects.toThrow( 'Error fetching referencing subjects' );
	} );

} );
