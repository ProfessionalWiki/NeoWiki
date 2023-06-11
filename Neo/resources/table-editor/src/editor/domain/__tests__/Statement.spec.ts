import { Statement } from '@/editor/domain/Statement';
import { PropertyName } from '@/editor/domain/Schema';
import { describe, expect, it } from 'vitest';

describe( 'Statement', () => {

	it( 'constructor creates a Statement with given property and value', () => {
		const property = new PropertyName( 'test' );
		const statement = new Statement( property, 'value' );

		expect( statement.propertyName.toString() ).toBe( 'test' );
		expect( statement.value ).toBe( 'value' );
	} );

	describe( 'hasValue', () => {

		it( 'returns false for undefined', () => {
			const statement = new Statement( new PropertyName( 'test' ), undefined );
			expect( statement.hasValue() ).toBe( false );
		} );

		it( 'returns true for strings', () => {
			const statement = new Statement( new PropertyName( 'test' ), '' );
			expect( statement.hasValue() ).toBe( true );
		} );

		it( 'returns false for empty arrays', () => {
			const statement = new Statement( new PropertyName( 'test' ), [] );
			expect( statement.hasValue() ).toBe( false );
		} );

		it( 'returns true for arrays with elements', () => {
			const statement = new Statement( new PropertyName( 'test' ), [ '' ] );
			expect( statement.hasValue() ).toBe( true );
		} );

	} );

} );
