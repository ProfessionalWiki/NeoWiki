import { Statement } from '@/editor/domain/Statement';
import { describe, expect, it } from 'vitest';
import { SubjectId } from '@/editor/domain/SubjectId';
import { StatementList, statementsToJson } from '@/editor/domain/StatementList';
import { PropertyName } from '@/editor/domain/PropertyDefinition';
import { Relation, RelationValue, newStringValue, newNumberValue, newBooleanValue } from '../Value';
import { PropertyDefinitionList } from '../PropertyDefinitionList';
import { newTextProperty } from '../valueFormats/Text';
import { newNumberProperty } from '../valueFormats/Number';
import { newSchema } from '../../../TestHelpers';

describe( 'StatementList', () => {

	const property1 = new PropertyName( 'property1' );
	const property2 = new PropertyName( 'property2' );
	const statement1 = new Statement( property1, newStringValue( 'value1' ) );
	const statement2 = new Statement( property2, newStringValue( 'value2' ) );

	it( 'constructs a StatementList from an array of Statements', () => {
		const statementList = new StatementList( [ statement1, statement2 ] );

		expect( statementList.get( property1 ) ).toEqual( statement1 );
		expect( statementList.get( property2 ) ).toEqual( statement2 );
	} );

	it( 'throws an error when constructing a StatementList with duplicate property names', () => {
		expect( () => new StatementList( [ statement1, statement1 ] ) )
			.toThrow( 'Cannot have two statements with property name: property1' );
	} );

	it( 'allows iteration over the Statements in the list', () => {
		const statementList = new StatementList( [ statement1, statement2 ] );
		const statements = [];

		for ( const statement of statementList ) {
			statements.push( statement );
		}

		expect( statements ).toEqual( [ statement1, statement2 ] );
	} );

	it( 'has method returns the correct values', () => {
		const statementList = new StatementList( [ statement1, statement2 ] );

		expect( statementList.has( new PropertyName( 'property1' ) ) ).toBe( true );
		expect( statementList.has( new PropertyName( 'unknownProperty' ) ) ).toBe( false );
		expect( statementList.has( new PropertyName( 'property2' ) ) ).toBe( true );
	} );

	it( 'filters out Statements with empty values', () => {
		const emptyStatement = new Statement( new PropertyName( 'emptyProperty' ), undefined );
		const statementList = new StatementList( [ statement1, emptyStatement, statement2 ] );

		const nonEmptyStatementList = statementList.withNonEmptyValues();

		expect( nonEmptyStatementList.get( property1 ) ).toEqual( statement1 );
		expect( nonEmptyStatementList.get( new PropertyName( 'emptyProperty' ) ) ).toBeUndefined();
		expect( nonEmptyStatementList.get( property2 ) ).toEqual( statement2 );
	} );

	it( 'getPropertyNames returns all property names in order', () => {
		const statements = new StatementList( [ statement1, statement2 ] );

		expect( statements.getPropertyNames() ).toEqual( [ property1, property2 ] );
	} );

	describe( 'getIdsOfReferencedSubjects', () => {

		it( 'should return empty list when there are no properties', () => {
			const statements = new StatementList( [] );

			expect( statements.getIdsOfReferencedSubjects() ).toEqual( new Set() );
		} );

		it( 'should return a list of referenced SubjectIds when relations exist', () => {
			const statements = new StatementList( [
				new Statement( new PropertyName( 'Property1' ), newStringValue( 'foo' ) ),
				new Statement(
					new PropertyName( 'Property2' ),
					new RelationValue( [
						new Relation( undefined, '00000000-0000-0000-0000-000000000001' )
					] )
				),
				new Statement(
					new PropertyName( 'Property3' ),
					new RelationValue( [
						new Relation( undefined, '00000000-0000-0000-0000-000000000002' ),
						new Relation( undefined, '00000000-0000-0000-0000-000000000003' )
					] )
				),
				new Statement( new PropertyName( 'Property4' ), newStringValue( 'bar' ) )
			] );

			expect( statements.getIdsOfReferencedSubjects() ).toEqual( new Set( [
				new SubjectId( '00000000-0000-0000-0000-000000000001' ),
				new SubjectId( '00000000-0000-0000-0000-000000000002' ),
				new SubjectId( '00000000-0000-0000-0000-000000000003' )
			] ) );
		} );

	} );

	it( 'converts to a property-value record correctly', () => {
		const statementList = new StatementList( [ statement1, statement2 ] );

		expect( statementList.asPropertyValueRecord() ).toEqual( {
			property1: newStringValue( 'value1' ),
			property2: newStringValue( 'value2' )
		} );
	} );

	it( 'constructs from a property-value record correctly', () => {
		const statementList = StatementList.fromJsonValues(
			{
				property1: 'value1',
				property2: 'value2'
			},
			newSchema( {
				properties: new PropertyDefinitionList( [
					newTextProperty( 'property1' ),
					newTextProperty( 'property2' )
				] )
			} )
		);

		expect( statementList.get( new PropertyName( 'property1' ) ) )
			.toEqual( new Statement( new PropertyName( 'property1' ), newStringValue( 'value1' ) ) );
		expect( statementList.get( new PropertyName( 'property2' ) ) )
			.toEqual( new Statement( new PropertyName( 'property2' ), newStringValue( 'value2' ) ) );
	} );

	it( 'throws an error when constructing from record with invalid property name', () => {

		expect( () => StatementList.fromJsonValues(
			{
				'': 'value1', // An empty string is not a valid PropertyName
				property2: 'value2'
			},
			newSchema()
		) )
			.toThrow( 'Invalid PropertyName' );
	} );

	it( 'from JSON values', () => {
		const statementList = StatementList.fromJsonValues(
			{
				p1: 'hello',
				p2: 42,
				p3: [ 'foo', 'bar' ]
			},
			newSchema( {
				properties: new PropertyDefinitionList( [
					newTextProperty( 'p1' ),
					newNumberProperty( 'p2' ),
					newTextProperty( 'p3' )
				] )
			} )
		);

		expect( statementList ).toStrictEqual( new StatementList( [
			new Statement( new PropertyName( 'p1' ), newStringValue( 'hello' ) ),
			new Statement( new PropertyName( 'p2' ), newNumberValue( 42 ) ),
			new Statement( new PropertyName( 'p3' ), newStringValue( 'foo', 'bar' ) )
		] ) );
	} );

} );

describe( 'statementsToJson', () => {

	it( 'converts all values into JSON representations', () => {
		const values = new StatementList( [
			new Statement( new PropertyName( 'value1' ), newStringValue( 'test' ) ),
			new Statement( new PropertyName( 'value2' ), newNumberValue( 123 ) ),
			new Statement( new PropertyName( 'value3' ), newBooleanValue( true ) ),
			new Statement( new PropertyName( 'value4' ), new RelationValue( [ new Relation( 'testId', 'testTarget' ) ] ) )
		] );

		const json = statementsToJson( values );

		expect( json ).toEqual( {
			value1: [ 'test' ],
			value2: 123,
			value3: true,
			value4: [
				{ id: 'testId', target: 'testTarget' }
			]
		} );
	} );

} );
