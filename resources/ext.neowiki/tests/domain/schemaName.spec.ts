import { describe, expect, it } from 'vitest';
import { normalizeSchemaName } from '@/domain/schemaName';

describe( 'normalizeSchemaName', () => {
	it( 'upper-cases the first character', () => {
		expect( normalizeSchemaName( 'person' ) ).toBe( 'Person' );
	} );

	it( 'only capitalises the first character, not later words', () => {
		expect( normalizeSchemaName( 'person of interest' ) ).toBe( 'Person of interest' );
	} );

	it( 'turns underscores into spaces and collapses runs of whitespace', () => {
		expect( normalizeSchemaName( 'Foo_Bar' ) ).toBe( 'Foo Bar' );
		expect( normalizeSchemaName( 'Foo   Bar' ) ).toBe( 'Foo Bar' );
	} );

	it( 'trims surrounding whitespace', () => {
		expect( normalizeSchemaName( '  Person  ' ) ).toBe( 'Person' );
	} );

	it.each( [
		[ 'a fragment', 'Museum#History', 'Museum' ],
		[ 'underscores around it', '_Person_', 'Person' ],
		[ 'an invisible direction mark', 'Person\u200F', 'Person' ],
		[ 'a decomposed accent', 'Cafe\u0301', 'Caf\u00E9' ],
	] )( 'resolves a name with %s to the page a save writes', ( _case, name, page ) => {
		expect( normalizeSchemaName( name ) ).toBe( page );
	} );

	it( 'leaves an already-canonical name unchanged', () => {
		expect( normalizeSchemaName( 'Validation Demo' ) ).toBe( 'Validation Demo' );
	} );
} );
