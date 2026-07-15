import { describe, expect, it } from 'vitest';
import { schemaReferenceName } from '@/domain/SchemaReference';

describe( 'schemaReferenceName', () => {

	it( 'returns a local reference unchanged', () => {
		expect( schemaReferenceName( 'Person' ) ).toBe( 'Person' );
	} );

	it( 'returns a local name that contains a colon unchanged', () => {
		expect( schemaReferenceName( 'Namespace:Person' ) ).toBe( 'Namespace:Person' );
	} );

	it( 'reduces a foreign reference to its name', () => {
		expect( schemaReferenceName( { source: 'otherwiki', name: 'Person' } ) ).toBe( 'Person' );
	} );

	it( 'reduces a foreign name that contains a colon to its name', () => {
		expect( schemaReferenceName( { source: 'otherwiki', name: 'Namespace:Person' } ) ).toBe( 'Namespace:Person' );
	} );

} );
