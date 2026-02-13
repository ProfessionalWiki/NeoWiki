import { describe, expect, it } from 'vitest';
import {
	newBooleanValue,
	newNumberValue,
	newRelation,
	newStringValue,
	relationValuesHaveSameTargets,
	RelationValue,
	type Value,
	valueToJson,
	ValueType,
} from '@/domain/Value';

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
			newRelation( 'testId', 's11111111111111' ),
		] );

		const json = valueToJson( value );

		expect( json ).toEqual( [
			{ id: 'testId', target: 's11111111111111' },
		] );
	} );

	it( 'converts a RelationValue with multiple relations into an array of objects', () => {
		const value = new RelationValue( [
			newRelation( 'testId1', 's11111111111111' ),
			newRelation( 'testId2', 's11111111111112' ),
		] );

		const json = valueToJson( value );

		expect( json ).toEqual( [
			{ id: 'testId1', target: 's11111111111111' },
			{ id: 'testId2', target: 's11111111111112' },
		] );
	} );

	it( 'throws an error when value is of unexpected type', () => {
		const value = {
			type: 'test' as ValueType,
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

describe( 'relationValuesHaveSameTargets', () => {

	it( 'returns true when both are undefined', () => {
		expect( relationValuesHaveSameTargets( undefined, undefined ) ).toBe( true );
	} );

	it( 'returns false when only one is undefined', () => {
		const value = new RelationValue( [ newRelation( undefined, 's11111111111111' ) ] );

		expect( relationValuesHaveSameTargets( value, undefined ) ).toBe( false );
		expect( relationValuesHaveSameTargets( undefined, value ) ).toBe( false );
	} );

	it( 'returns true when both have the same targets', () => {
		const a = new RelationValue( [ newRelation( 'id-a', 's11111111111111' ) ] );
		const b = new RelationValue( [ newRelation( 'id-b', 's11111111111111' ) ] );

		expect( relationValuesHaveSameTargets( a, b ) ).toBe( true );
	} );

	it( 'returns false when targets differ', () => {
		const a = new RelationValue( [ newRelation( undefined, 's11111111111111' ) ] );
		const b = new RelationValue( [ newRelation( undefined, 's22222222222222' ) ] );

		expect( relationValuesHaveSameTargets( a, b ) ).toBe( false );
	} );

	it( 'returns false when lengths differ', () => {
		const a = new RelationValue( [
			newRelation( undefined, 's11111111111111' ),
			newRelation( undefined, 's22222222222222' ),
		] );
		const b = new RelationValue( [ newRelation( undefined, 's11111111111111' ) ] );

		expect( relationValuesHaveSameTargets( a, b ) ).toBe( false );
	} );

	it( 'compares targets regardless of relation IDs', () => {
		const a = new RelationValue( [
			newRelation( 'first-id', 's11111111111111' ),
			newRelation( 'second-id', 's22222222222222' ),
		] );
		const b = new RelationValue( [
			newRelation( 'different-id', 's11111111111111' ),
			newRelation( 'another-id', 's22222222222222' ),
		] );

		expect( relationValuesHaveSameTargets( a, b ) ).toBe( true );
	} );

} );
