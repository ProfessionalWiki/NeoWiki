import { Statement } from '@neo/domain/Statement';
import { describe, expect, it } from 'vitest';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newStringValue } from '../Value';
import { TextFormat } from '../valueFormats/Text';

describe( 'Statement', () => {

	it( 'constructor creates a Statement with given property and value', () => {
		const property = new PropertyName( 'test' );
		const statement = new Statement( property, TextFormat.formatName, newStringValue( 'value' ) );

		expect( statement.propertyName.toString() ).toBe( 'test' );
		expect( statement.value ).toEqual( newStringValue( 'value' ) );
	} );

	describe( 'hasValue', () => {

		it( 'returns false for undefined', () => {
			const statement = new Statement( new PropertyName( 'test' ), TextFormat.formatName, undefined );
			expect( statement.hasValue() ).toBe( false );
		} );

		it( 'returns true for values, even when they are empty strings', () => {
			const statement = new Statement( new PropertyName( 'test' ), TextFormat.formatName, newStringValue( '' ) );
			expect( statement.hasValue() ).toBe( true );
		} );

		it( 'returns true values, even when they are empty arrays', () => {
			const statement = new Statement( new PropertyName( 'test' ), TextFormat.formatName, newStringValue() );
			expect( statement.hasValue() ).toBe( true );
		} );

	} );

} );
