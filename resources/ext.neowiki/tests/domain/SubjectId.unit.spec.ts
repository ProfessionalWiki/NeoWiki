import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { SubjectId } from '@/domain/SubjectId';

interface ValidVector {
	case: string;
	input: string;
	text: string;
	source: string | null;
	localId: string;
}

interface InvalidVector {
	case: string;
	input: string;
}

/**
 * The shared vectors in tests/vectors/subject-ids.json, which the PHP suite runs against its own
 * SubjectId, so both implementations answer the same for every case. The file's canonicalization
 * section is deliberately not read here: it needs the local Source key, which only the PHP write path
 * has, and the frontend only ever sees ids already in canonical form.
 */
const vectors = JSON.parse( readFileSync(
	// Vitest runs from resources/ext.neowiki, so the extension root is two levels up.
	resolve( process.cwd(), '../../tests/vectors/subject-ids.json' ),
	'utf8',
) ) as { valid: ValidVector[]; invalid: InvalidVector[] };

describe( 'SubjectId', () => {

	it.each( vectors.valid )( 'reads $case', ( vector: ValidVector ) => {
		const id = new SubjectId( vector.input );

		expect( id.text ).toBe( vector.text );
		expect( id.source ).toBe( vector.source );
		expect( id.localId ).toBe( vector.localId );
	} );

	it.each( vectors.invalid )( 'rejects $case', ( vector: InvalidVector ) => {
		expect( SubjectId.isValid( vector.input ) ).toBe( false );
		expect( () => new SubjectId( vector.input ) ).toThrowError();
	} );

	it( 'reads a bare id as local', () => {
		const subjectId = new SubjectId( 's11111111111111' );

		expect( subjectId.isLocal() ).toBe( true );
	} );

	it( 'reads a qualified id as not local', () => {
		expect( new SubjectId( 'otherwiki:Q42' ).isLocal() ).toBe( false );
	} );

	it( 'does not accept a qualified id as a local id', () => {
		expect( SubjectId.isValidLocalId( 's11111111111111' ) ).toBe( true );
		expect( SubjectId.isValidLocalId( 'otherwiki:s11111111111111' ) ).toBe( false );
	} );

} );
