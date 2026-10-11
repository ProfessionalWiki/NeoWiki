import { describe, expect, it } from 'vitest';
import { isLocalSchemaReference, schemaReferenceName, schemaReferenceText } from '@/domain/SchemaReference';

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

} );

describe( 'schemaReferenceText', () => {

	it( 'reads a Schema of this wiki as its name', () => {
		expect( schemaReferenceText( 'ISO:9001' ) ).toBe( 'ISO:9001' );
	} );

	it( 'prefixes the name of a Schema of another Source with its source key', () => {
		expect( schemaReferenceText( { source: 'otherwiki', name: 'Person' } ) ).toBe( 'otherwiki:Person' );
	} );

} );

describe( 'isLocalSchemaReference', () => {

	it( 'is true for a bare name, which always means this wiki', () => {
		expect( isLocalSchemaReference( 'Product' ) ).toBe( true );
	} );

	it( 'is false for a reference carrying a Source', () => {
		expect( isLocalSchemaReference( { source: 'otherwiki', name: 'Person' } ) ).toBe( false );
	} );

} );
