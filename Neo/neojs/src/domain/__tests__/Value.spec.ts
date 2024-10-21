import { describe, expect, it } from 'vitest';
import {
	newBooleanValue,
	newNumberValue,
	newStringValue,
	Relation,
	RelationValue,
	type Value,
	valueToJson,
	ValueType
} from '@neo/domain/Value';

describe( 'valueToJson', () => {

	it( 'converts a StringValue with one string into an array of strings', () => {
		const value = newStringValue( 'test' );

		const json = valueToJson( value );

		expect( json ).toEqual( [ 'test' ] );
	} );

	it( 'converts StringValue with multiple strings into an array of strings', () => {
		const value = newStringValue( 'test1', 'test2' );

		const json = valueToJson( value );

		expect( json ).toEqual( [ 'test1', 'test2' ] );
	} );

	it( 'converts a NumberValue into a single number', () => {
		const value = newNumberValue( 123 );

		const json = valueToJson( value );

		expect( json ).toEqual( 123 );
	} );

	it( 'converts a BooleanValue into a boolean', () => {
		const value = newBooleanValue( true );

		const json = valueToJson( value );

		expect( json ).toEqual( true );
	} );

	it( 'converts a RelationValue with a single relation into an array of objects', () => {
		const value = new RelationValue( [
			new Relation( 'testId', 'testTarget' )
		] );

		const json = valueToJson( value );

		expect( json ).toEqual( [
			{ id: 'testId', target: 'testTarget' }
		] );
	} );

	it( 'converts a RelationValue with multiple relations into an array of objects', () => {
		const value = new RelationValue( [
			new Relation( 'testId1', 'testTarget1' ),
			new Relation( 'testId2', 'testTarget2' )
		] );

		const json = valueToJson( value );

		expect( json ).toEqual( [
			{ id: 'testId1', target: 'testTarget1' },
			{ id: 'testId2', target: 'testTarget2' }
		] );
	} );

	it( 'throws an error when value is of unexpected type', () => {
		const value = {
			type: 'test' as ValueType
		} as unknown as Value;

		expect( () => valueToJson( value ) ).toThrow( 'Unsupported value type: test' );
	} );

} );

describe( 'newStringValue', () => {

	it( 'takes both string arrays and multiple string values', () => {
		expect( newStringValue( 'foo', 'bar' ) ).toEqual( newStringValue( [ 'foo', 'bar' ] ) );
	} );

	it( 'omits empty strings', () => {
		expect( newStringValue( '', 'foo', '', 'bar', '' ) ).toEqual( newStringValue( 'foo', 'bar' ) );
	} );

	it( 'omits space-only strings', () => {
		expect( newStringValue( ' ', 'foo', ' ', 'bar', ' ' ) ).toEqual( newStringValue( 'foo', 'bar' ) );
	} );

	it( 'trims strings', () => {
		expect( newStringValue( '   preceding', 'tailing ', ' both    ', ' keeps middle spaces ' ) )
			.toEqual( newStringValue( 'preceding', 'tailing', 'both', 'keeps middle spaces' ) );
	} );

} );
