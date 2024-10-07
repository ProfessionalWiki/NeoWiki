import { describe, expect, it } from 'vitest';
import { newNumberValue, newStringValue, Relation, RelationValue } from '@neo/domain/Value';
import { Neo } from '@neo/Neo';

describe( 'ValueDeserializer', () => {

	const deserializer = Neo.getInstance().getValueDeserializer();

	it( 'converts a single string into a StringValue', () => {
		const value = deserializer.deserialize( 'test', 'text' );

		expect( value ).toEqual( newStringValue( 'test' ) );
	} );

	it( 'converts an array of strings into a StringValue', () => {
		const value = deserializer.deserialize( [ 'test1', 'test2' ], 'text' );

		expect( value ).toEqual( newStringValue( 'test1', 'test2' ) );
	} );

	it( 'converts a single number into a NumberValue', () => {
		const value = deserializer.deserialize( 123, 'number' );

		expect( value ).toEqual( newNumberValue( 123 ) );
	} );

	it( 'deserializes RelationValue with multiple relations', () => {
		const json = [
			{ id: 'r1vd1111rrrrrrr1', target: 's1vd1111sssssss1' },
			{ id: 'r1vd1111rrrrrrr2', target: 's1vd1111sssssss2' }
		];

		const value = deserializer.deserialize( json, 'relation' );

		expect( value ).toEqual( new RelationValue( [
			new Relation( 'r1vd1111rrrrrrr1', 's1vd1111sssssss1' ),
			new Relation( 'r1vd1111rrrrrrr2', 's1vd1111sssssss2' )
		] ) );
	} );

	it( 'deserializes RelationValue with no relations', () => {
		const value = deserializer.deserialize( [], 'relation' );

		expect( value ).toEqual( new RelationValue( [] ) );
	} );

	it( 'throws error on invalid relation json', () => {
		expect( () => deserializer.deserialize( { foo: 'bar' }, 'relation' ) ).toThrow( 'Invalid relation value: {"foo":"bar"}' );
	} );

} );
