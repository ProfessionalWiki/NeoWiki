import { Statement } from '@/domain/Statement';
import { describe, expect, it } from 'vitest';
import { PropertyName } from '@/domain/PropertyDefinition';
import { TextType } from '@/domain/propertyTypes/Text';
import { newStringValue } from '@/domain/Value';

describe( 'Statement', () => {

	it( 'constructor creates a Statement with given property and value', () => {
		const property = new PropertyName( 'test' );
		const statement = new Statement( property, TextType.typeName, newStringValue( 'value' ) );

		expect( statement.propertyName.toString() ).toBe( 'test' );
		expect( statement.value ).toEqual( newStringValue( 'value' ) );
	} );

	describe( 'hasValue', () => {

		it( 'returns false for undefined', () => {
			const statement = new Statement( new PropertyName( 'test' ), TextType.typeName, undefined );
			expect( statement.hasValue() ).toBe( false );
		} );

		it( 'returns true for values, even when they are empty strings', () => {
			const statement = new Statement( new PropertyName( 'test' ), TextType.typeName, newStringValue( '' ) );
			expect( statement.hasValue() ).toBe( true );
		} );

		it( 'returns true values, even when they are empty arrays', () => {
			const statement = new Statement( new PropertyName( 'test' ), TextType.typeName, newStringValue() );
			expect( statement.hasValue() ).toBe( true );
		} );

	} );

	describe( 'withValue', () => {
		it( 'returns new Statement with updated value', () => {
			const property = new PropertyName( 'test' );
			const originalStatement = new Statement( property, TextType.typeName, newStringValue( 'original' ) );
			const newValue = newStringValue( 'updated' );

			const updatedStatement = originalStatement.withValue( newValue );

			expect( updatedStatement ).not.toBe( originalStatement );
			expect( updatedStatement.propertyName ).toBe( originalStatement.propertyName );
			expect( updatedStatement.propertyType ).toBe( originalStatement.propertyType );
			expect( updatedStatement.value ).toEqual( newValue );
		} );

		it( 'can remove value by setting to undefined', () => {
			const property = new PropertyName( 'test' );
			const originalStatement = new Statement( property, TextType.typeName, newStringValue( 'original' ) );

			const updatedStatement = originalStatement.withValue( undefined );

			expect( updatedStatement.value ).toBeUndefined();
			expect( updatedStatement.hasValue() ).toBe( false );
		} );
	} );

} );
