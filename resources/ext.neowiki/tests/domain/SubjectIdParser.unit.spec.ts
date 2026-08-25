import { readFileSync } from 'node:fs';
import { resolve } from 'node:path';
import { describe, expect, it } from 'vitest';
import { SubjectId } from '@/domain/SubjectId';
import { SubjectIdParser } from '@/domain/SubjectIdParser';

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
 * SubjectIdParser, so both implementations answer the same for every case.
 */
const vectors = JSON.parse( readFileSync(
	// Vitest runs from resources/ext.neowiki, so the extension root is two levels up.
	resolve( process.cwd(), '../../tests/vectors/subject-ids.json' ),
	'utf8'
) ) as { localSourceKey: string; valid: ValidVector[]; invalid: InvalidVector[] };

const parser = new SubjectIdParser( vectors.localSourceKey );

describe( 'SubjectIdParser', () => {

	it.each( vectors.valid )( 'parses $case', ( vector: ValidVector ) => {
		const id = parser.parseOrThrow( vector.input );

		expect( id.text ).toBe( vector.text );
		expect( id.source ).toBe( vector.source );
		expect( id.localId ).toBe( vector.localId );
	} );

	it.each( vectors.invalid )( 'returns null for $case', ( vector: InvalidVector ) => {
		expect( parser.parse( vector.input ) ).toBeNull();
	} );

	it( 'gives an explicitly local id the same identity as its bare form', () => {
		const qualified = parser.parseOrThrow( `${ vectors.localSourceKey }:s11111111111111` );

		expect( qualified.text ).toBe( new SubjectId( 's11111111111111' ).text );
	} );

	it( 'leaves another wiki\'s key in place', () => {
		expect( parser.parseOrThrow( 'otherwiki:s11111111111111' ).source ).toBe( 'otherwiki' );
	} );

} );
