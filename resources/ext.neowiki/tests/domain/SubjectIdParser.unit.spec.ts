// @vitest-environment node
import { describe, expect, it } from 'vitest';
import { readFileSync } from 'node:fs';
import { SubjectIdParser } from '@/domain/SubjectIdParser';

interface Vector {
	name: string;
	input: string;
	valid: boolean;
	canonicalText?: string;
	source?: string | null;
	localId?: string;
}

const vectors = JSON.parse(
	readFileSync( new URL( '../../../../tests/subject-id-vectors.json', import.meta.url ), 'utf8' ),
) as { localSourceKey: string; cases: Vector[] };

const parser = new SubjectIdParser( vectors.localSourceKey );
const validCases = vectors.cases.filter( ( vector ) => vector.valid ) as Required<Vector>[];
const invalidCases = vectors.cases.filter( ( vector ) => !vector.valid );

describe( 'SubjectIdParser', () => {

	it.each( validCases )( 'parses: $name', ( { input, canonicalText, source, localId } ) => {
		const id = parser.parse( input );

		expect( id.text ).toBe( canonicalText );
		expect( id.source ).toBe( source );
		expect( id.localId ).toBe( localId );
	} );

	it.each( validCases )( 'canonical text parses to itself: $name', ( { canonicalText } ) => {
		expect( parser.parse( canonicalText ).text ).toBe( canonicalText );
	} );

	it.each( invalidCases )( 'rejects: $name', ( { input } ) => {
		expect( () => parser.parse( input ) ).toThrowError();
	} );

	it( 'accepts a local source key outside the key grammar', () => {
		const weirdKeyParser = new SubjectIdParser( 'weird~key' );

		expect( weirdKeyParser.parse( 's11111111111111' ).text ).toBe( 's11111111111111' );
	} );

} );
