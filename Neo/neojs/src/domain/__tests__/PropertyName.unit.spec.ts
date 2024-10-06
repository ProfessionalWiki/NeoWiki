import { describe, expect, it } from 'vitest';
import { PropertyName } from '@neo/domain/PropertyDefinition';

describe( 'PropertyName constructor', () => {

	it( 'creates a valid PropertyName', () => {
		const id = new PropertyName( 'test' );
		expect( id.toString() ).toBe( 'test' );
	} );

	it( 'throws an error for an empty string', () => {
		expect( () => new PropertyName( '' ) ).toThrow( 'Invalid PropertyName' );
	} );

} );
