import { describe, expect, it } from 'vitest';
import {
	jsonToValue,
	newBooleanValue,
	newNumberValue,
	newStringValue,
	Relation,
	RelationValue,
	type Value,
	valueToJson,
	ValueType
} from '@/editor/domain/Value';

describe( 'jsonToValue', () => {

	it( 'converts a single string into a StringValue', () => {
		const json = 'test';

		const value = jsonToValue( json );

		expect( value ).toEqual( newStringValue( 'test' ) );
	} );

	it( 'converts an array of strings into a StringValue', () => {
		const json = [ 'test1', 'test2' ];

		const value = jsonToValue( json );

		expect( value ).toEqual( newStringValue( 'test1', 'test2' ) );
	} );

	it( 'converts a single number into a NumberValue', () => {
		const json = 123;

		const value = jsonToValue( json );

		expect( value ).toEqual( newNumberValue( 123 ) );
	} );

	it( 'converts a boolean into a BooleanValue', () => {
		const json = true;

		const value = jsonToValue( json );

		expect( value ).toEqual( newBooleanValue( true ) );
	} );

	it( 'converts an object with target into a RelationValue', () => {
		const json = {
			id: 'testId',
			target: 'testTarget'
		};

		const value = jsonToValue( json );

		expect( value ).toEqual( new RelationValue( [ new Relation( 'testId', 'testTarget' ) ] ) );
	} );

	it( 'converts an array of objects with target into a RelationValue', () => {
		const json = [
			{ id: 'testId1', target: 'testTarget1' },
			{ id: 'testId2', target: 'testTarget2' }
		];

		const value = jsonToValue( json );

		expect( value ).toEqual( new RelationValue( [
			new Relation( 'testId1', 'testTarget1' ),
			new Relation( 'testId2', 'testTarget2' )
		] ) );
	} );

	it( 'throws an error when input is of unexpected type', () => {
		const json = { foo: 'bar' };

		expect( () => jsonToValue( json ) ).toThrow( 'Invalid value: {"foo":"bar"}' );
	} );

	it( 'throws an error when input is an array of unexpected type', () => {
		const json = [ 123, 'test' ];

		expect( () => jsonToValue( json ) ).toThrow( 'Invalid value array: [123,"test"]' );
	} );

	it( 'converts an empty array into a string value', () => {
		const json: string[] = [];

		expect( jsonToValue( json, ValueType.String ) ).toEqual( newStringValue() );
	} );

	it( 'converts an empty array into a relation value', () => {
		const json: string[] = [];

		expect( jsonToValue( json, ValueType.Relation ) ).toEqual( new RelationValue( [] ) );
	} );

	it( 'throws on empty array without type info', () => {
		const json: string[] = [];

		expect( () => jsonToValue( json, undefined ) ).toThrow();
	} );

} );

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
