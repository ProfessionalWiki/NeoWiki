import { describe, expect, it } from 'vitest';
import {
	isValueEmpty,
	newBooleanValue,
	newMonolingualTextValue,
	newNumberValue,
	newRelation,
	newStringValue,
	newUnregisteredTypeValue,
	relationValueWithTargets,
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

	it( 'converts a MonolingualTextValue into an array of text and language objects', () => {
		const value = newMonolingualTextValue( [
			{ text: 'Zinema', language: 'eu' },
			{ text: 'Cine', language: 'es' },
		] );

		const json = valueToJson( value );

		expect( json ).toEqual( [
			{ text: 'Zinema', language: 'eu' },
			{ text: 'Cine', language: 'es' },
		] );
	} );

	it( 'throws an error when value is of unexpected type', () => {
		const value = {
			type: 'test' as ValueType,
		} as unknown as Value;

		expect( () => valueToJson( value ) ).toThrow( 'Unsupported value type: test' );
	} );

	it( 'round-trips an UnregisteredTypeValue back to its raw stored value', () => {
		const raw = { custom: [ 'red', 'green' ], shade: 3 };

		const json = valueToJson( newUnregisteredTypeValue( 'color', raw ) );

		expect( json ).toBe( raw );
	} );

} );

describe( 'newUnregisteredTypeValue', () => {

	it( 'captures the original type name and the raw value', () => {
		const value = newUnregisteredTypeValue( 'color', [ '#fff' ] );

		expect( value.type ).toBe( ValueType.UnregisteredType );
		expect( value.typeName ).toBe( 'color' );
		expect( value.raw ).toEqual( [ '#fff' ] );
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

describe( 'newMonolingualTextValue', () => {

	it( 'trims each text', () => {
		expect( newMonolingualTextValue( [ { text: '  Zinema ', language: 'eu' } ] ).parts )
			.toEqual( [ { text: 'Zinema', language: 'eu' } ] );
	} );

	it( 'omits parts whose text is empty or blank', () => {
		const value = newMonolingualTextValue( [
			{ text: '', language: 'fr' },
			{ text: '   ', language: 'de' },
			{ text: 'Cine', language: 'es' },
		] );

		expect( value.parts ).toEqual( [ { text: 'Cine', language: 'es' } ] );
	} );

	it( 'lowercases each language tag', () => {
		expect( newMonolingualTextValue( [ { text: 'Cinema', language: 'pt-BR' } ] ).parts )
			.toEqual( [ { text: 'Cinema', language: 'pt-br' } ] );
	} );

	it( 'keeps several parts in the same language', () => {
		const value = newMonolingualTextValue( [
			{ text: 'Cine', language: 'es' },
			{ text: 'Pelicula', language: 'es' },
		] );

		expect( value.parts ).toEqual( [
			{ text: 'Cine', language: 'es' },
			{ text: 'Pelicula', language: 'es' },
		] );
	} );

} );

describe( 'relationValueWithTargets', () => {

	it( 'keeps the Relation of each target the value already points at, in the order of the targets', () => {
		const current = new RelationValue( [
			newRelation( 'r11111111111111', 's11111111111111' ),
			newRelation( 'r22222222222222', 's22222222222222' ),
		] );

		expect( relationValueWithTargets( current, [ 's22222222222222', 's11111111111111' ] ).relations ).toEqual( [
			newRelation( 'r22222222222222', 's22222222222222' ),
			newRelation( 'r11111111111111', 's11111111111111' ),
		] );
	} );

	it( 'gives a newly listed target a new Relation in place of an unlisted one', () => {
		const current = new RelationValue( [
			newRelation( 'r11111111111111', 's11111111111111' ),
			newRelation( 'r22222222222222', 's22222222222222' ),
		] );

		expect( relationValueWithTargets( current, [ 's11111111111111', 's33333333333333' ] ).relations ).toEqual( [
			newRelation( 'r11111111111111', 's11111111111111' ),
			newRelation( undefined, 's33333333333333' ),
		] );
	} );

	it( 'leaves the current value as it was', () => {
		const current = new RelationValue( [
			newRelation( 'r11111111111111', 's11111111111111' ),
			newRelation( 'r22222222222222', 's22222222222222' ),
		] );

		relationValueWithTargets( current, [ 's11111111111111', 's22222222222222' ] );

		expect( current.relations ).toEqual( [
			newRelation( 'r11111111111111', 's11111111111111' ),
			newRelation( 'r22222222222222', 's22222222222222' ),
		] );
	} );

	it( 'keeps both Relations to a target the value points at twice', () => {
		const current = new RelationValue( [
			newRelation( 'r11111111111111', 's11111111111111' ),
			newRelation( 'r22222222222222', 's11111111111111' ),
		] );

		expect( relationValueWithTargets( current, [ 's11111111111111', 's11111111111111' ] ).relations ).toEqual( [
			newRelation( 'r11111111111111', 's11111111111111' ),
			newRelation( 'r22222222222222', 's11111111111111' ),
		] );
	} );

} );

describe( 'isValueEmpty', () => {

	it( 'returns true for undefined', () => {
		expect( isValueEmpty( undefined ) ).toBe( true );
	} );

	it( 'returns true for a StringValue with no parts', () => {
		expect( isValueEmpty( newStringValue() ) ).toBe( true );
	} );

	it( 'treats whitespace-only StringValue parts as empty', () => {
		expect( isValueEmpty( newStringValue( '' ) ) ).toBe( true );
	} );

	it( 'returns false for a StringValue with content', () => {
		expect( isValueEmpty( newStringValue( 'x' ) ) ).toBe( false );
	} );

	it( 'returns false for NumberValue with 0 (zero is a value)', () => {
		expect( isValueEmpty( newNumberValue( 0 ) ) ).toBe( false );
	} );

	it( 'returns false for BooleanValue with false (false is a value)', () => {
		expect( isValueEmpty( newBooleanValue( false ) ) ).toBe( false );
	} );

	it( 'returns true for an empty RelationValue', () => {
		expect( isValueEmpty( new RelationValue( [] ) ) ).toBe( true );
	} );

	it( 'returns false for a RelationValue carrying relations', () => {
		const relation = newRelation( undefined, 's11111111111111' );
		expect( isValueEmpty( new RelationValue( [ relation ] ) ) ).toBe( false );
	} );

	it( 'returns true for a MonolingualTextValue with no parts', () => {
		expect( isValueEmpty( newMonolingualTextValue( [] ) ) ).toBe( true );
	} );

	it( 'returns false for a MonolingualTextValue carrying a text', () => {
		expect( isValueEmpty( newMonolingualTextValue( [ { text: 'Zinema', language: 'eu' } ] ) ) ).toBe( false );
	} );

	it( 'returns false for an UnregisteredTypeValue (it still carries stored data)', () => {
		expect( isValueEmpty( newUnregisteredTypeValue( 'color', [ '#fff' ] ) ) ).toBe( false );
	} );

} );
