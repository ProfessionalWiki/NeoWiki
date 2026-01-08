import { Statement } from '@/domain/Statement';
import { describe, expect, it } from 'vitest';
import { SubjectId } from '@/domain/SubjectId';
import { StatementList, statementsToJson } from '@/domain/StatementList';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newNumberValue, newRelation, newStringValue, RelationValue } from '@/domain/Value';
import { Neo } from '@/Neo';
import { TextType } from '@/domain/propertyTypes/Text';
import { NumberType } from '@/domain/propertyTypes/Number';
import { RelationType } from '@/domain/propertyTypes/Relation';

describe( 'StatementList', () => {

	const property1 = new PropertyName( 'property1' );
	const property2 = new PropertyName( 'property2' );
	const statement1 = new Statement( property1, TextType.typeName, newStringValue( 'value1' ) );
	const statement2 = new Statement( property2, TextType.typeName, newStringValue( 'value2' ) );

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
		const emptyStatement = new Statement( new PropertyName( 'emptyProperty' ), TextType.typeName, undefined );
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

		it( 'should return empty list when there are no statements', () => {
			const statements = new StatementList( [] );

			expect( statements.getIdsOfReferencedSubjects() ).toEqual( new Set() );
		} );

		it( 'should return a list of referenced SubjectIds when relations exist', () => {
			const statements = new StatementList( [
				new Statement( new PropertyName( 'Property1' ), TextType.typeName, newStringValue( 'foo' ) ),
				new Statement(
					new PropertyName( 'Property2' ),
					RelationType.typeName,
					new RelationValue( [
						newRelation( undefined, 's11111111111111' )
					] )
				),
				new Statement(
					new PropertyName( 'Property3' ),
					RelationType.typeName,
					new RelationValue( [
						newRelation( undefined, 's11111111111112' ),
						newRelation( undefined, 's11111111111113' )
					] )
				),
				new Statement( new PropertyName( 'Property4' ), TextType.typeName, newStringValue( 'bar' ) )
			] );

			expect( statements.getIdsOfReferencedSubjects() ).toEqual( new Set( [
				new SubjectId( 's11111111111111' ),
				new SubjectId( 's11111111111112' ),
				new SubjectId( 's11111111111113' )
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
		const statementList = Neo.getInstance().getSubjectDeserializer().deserializeStatements(
			{
				property1: {
					value: 'value1',
					type: TextType.typeName
				},
				property2: {
					value: 'value2',
					type: TextType.typeName
				}
			}
		);

		expect( statementList.get( new PropertyName( 'property1' ) ) )
			.toEqual( new Statement( new PropertyName( 'property1' ), TextType.typeName, newStringValue( 'value1' ) ) );
		expect( statementList.get( new PropertyName( 'property2' ) ) )
			.toEqual( new Statement( new PropertyName( 'property2' ), TextType.typeName, newStringValue( 'value2' ) ) );
	} );

	it( 'throws an error when constructing from record with invalid property name', () => {

		expect( () => Neo.getInstance().getSubjectDeserializer().deserializeStatements(
			{
				'': {
					value: 'value1',
					type: TextType.typeName
				}, // An empty string is not a valid PropertyName
				property2: {
					value: 'value2',
					type: TextType.typeName
				}
			}
		) )
			.toThrow( 'Invalid PropertyName' );
	} );

	it( 'from JSON values', () => {
		const statementList = Neo.getInstance().getSubjectDeserializer().deserializeStatements(
			{
				p1: {
					value: 'hello',
					type: TextType.typeName
				},
				p2: {
					value: 42,
					type: NumberType.typeName
				},
				p3: {
					value: [ 'foo', 'bar' ],
					type: TextType.typeName
				}
			}
		);

		expect( statementList ).toStrictEqual( new StatementList( [
			new Statement( new PropertyName( 'p1' ), TextType.typeName, newStringValue( 'hello' ) ),
			new Statement( new PropertyName( 'p2' ), NumberType.typeName, newNumberValue( 42 ) ),
			new Statement( new PropertyName( 'p3' ), TextType.typeName, newStringValue( 'foo', 'bar' ) )
		] ) );
	} );

} );

describe( 'statementsToJson', () => {

	it( 'converts all values into JSON representations', () => {
		const values = new StatementList( [
			new Statement( new PropertyName( 'value1' ), TextType.typeName, newStringValue( 'test' ) ),
			new Statement( new PropertyName( 'value2' ), NumberType.typeName, newNumberValue( 123 ) ),
			new Statement( new PropertyName( 'value4' ), RelationType.typeName, new RelationValue( [ newRelation( 'testId', 's11111111111111' ) ] ) )
		] );

		const json = statementsToJson( values );

		expect( json ).toEqual( {
			value1: {
				value: [ 'test' ],
				propertyType: TextType.typeName
			},
			value2: {
				value: 123,
				propertyType: NumberType.typeName
			},
			value4: {
				value: [
					{ id: 'testId', target: 's11111111111111' }
				],
				propertyType: RelationType.typeName
			}
		} );
	} );

	it( 'converts an empty number to null', () => {
		const values = new StatementList( [
			new Statement( new PropertyName( 'EmptyValue' ), NumberType.typeName, newNumberValue( NaN ) )
		] );

		const json = statementsToJson( values );

		expect( json ).toEqual( {
			EmptyValue: null
		} );
	} );

} );
