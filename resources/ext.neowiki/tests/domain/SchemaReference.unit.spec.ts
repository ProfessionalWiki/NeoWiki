import { describe, expect, it } from 'vitest';
import {
	isLocalSchemaReference,
	schemaReferenceName,
	schemaReferenceText,
} from '@/domain/SchemaReference';

describe( 'schemaReferenceName', () => {

	it( 'reads a bare string as the name of a Schema of this wiki', () => {
		expect( schemaReferenceName( 'Product' ) ).toBe( 'Product' );
	} );

	it( 'keeps a colon in a local name rather than splitting it into a Source', () => {
		expect( schemaReferenceName( 'ISO:9001' ) ).toBe( 'ISO:9001' );
	} );

	it( 'takes the name out of a Source-qualified reference', () => {
		expect( schemaReferenceName( { source: 'otherwiki', name: 'Person' } ) ).toBe( 'Person' );
	} );

	it( 'has no name to give for an absent reference', () => {
		expect( schemaReferenceName( undefined ) ).toBe( '' );
	} );

} );

describe( 'isLocalSchemaReference', () => {

	it( 'is true for a bare name, which always means this wiki', () => {
		expect( isLocalSchemaReference( 'Product' ) ).toBe( true );
	} );

	it( 'is false for a reference carrying a Source', () => {
		expect( isLocalSchemaReference( { source: 'otherwiki', name: 'Person' } ) ).toBe( false );
	} );

	it( 'is false when there is no reference at all', () => {
		expect( isLocalSchemaReference( undefined ) ).toBe( false );
	} );

} );

describe( 'schemaReferenceText', () => {

	it( 'shows a local Schema under its plain name', () => {
		expect( schemaReferenceText( 'Product' ) ).toBe( 'Product' );
	} );

	it( 'shows the Source, so a foreign Schema is not read as a local one of the same name', () => {
		expect( schemaReferenceText( { source: 'otherwiki', name: 'Person' } ) ).toBe( 'otherwiki:Person' );
	} );

} );
