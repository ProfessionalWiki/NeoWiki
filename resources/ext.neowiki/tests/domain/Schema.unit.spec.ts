import { describe, expect, it } from 'vitest';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { newTextProperty, TextType } from '@/domain/propertyTypes/Text';
import { newSchema } from '@/TestHelpers';
import { newNumberProperty, NumberType } from '@/domain/propertyTypes/Number';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';

describe( 'Schema', () => {

	describe( 'getPropertyDefinition', () => {

		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Wrong' } ),
				newTextProperty( { name: 'MyText' } ),
				newTextProperty( { name: 'AlsoWrong' } ),
			] ),
		} );

		it( 'returns undefined for unknown property', () => {
			expect( schema.getPropertyDefinition( '404' ) ).toBeUndefined();
		} );

		it( 'returns known property definition', () => {
			const property = schema.getPropertyDefinition( 'MyText' );

			expect( property ).toStrictEqual( newTextProperty( { name: 'MyText' } ) );
		} );

	} );

	describe( 'withName', () => {

		it( 'returns a new Schema with the updated name', () => {
			const originalSchema = newSchema();

			const updatedSchema = originalSchema.withName( 'Updated Schema' );

			expect( updatedSchema.getName() ).toBe( 'Updated Schema' );
			expect( updatedSchema.getDescription() ).toBe( originalSchema.getDescription() );
			expect( updatedSchema.getPropertyDefinitions() ).toEqual( originalSchema.getPropertyDefinitions() );
			expect( updatedSchema ).not.toBe( originalSchema );
		} );

	} );

	describe( 'withDescription', () => {

		it( 'returns a new Schema with the updated description', () => {
			const originalSchema = newSchema();

			const updatedSchema = originalSchema.withDescription( 'Updated description' );

			expect( updatedSchema.getDescription() ).toBe( 'Updated description' );
			expect( updatedSchema.getName() ).toBe( originalSchema.getName() );
			expect( updatedSchema.getPropertyDefinitions() ).toEqual( originalSchema.getPropertyDefinitions() );
			expect( updatedSchema ).not.toBe( originalSchema );
		} );

	} );

	describe( 'withAddedPropertyDefinition', () => {

		it( 'adds a Property Definition when Schema has no properties', () => {
			const originalSchema = newSchema();

			const addedProperty = newTextProperty();

			const updatedSchema = originalSchema.withAddedPropertyDefinition( addedProperty );

			expect( updatedSchema.getPropertyDefinitions().asRecord() ).toEqual( {
				[ addedProperty.name.toString() ]: addedProperty,
			} );
		} );

		it( 'adds a Property Definition when Schema has properties', () => {
			const existingProperty = newTextProperty();

			const originalSchema = newSchema( {
				properties: new PropertyDefinitionList( [ existingProperty ] ),
			} );

			const addedProperty = newNumberProperty();

			const updatedSchema = originalSchema.withAddedPropertyDefinition( addedProperty );

			expect( updatedSchema.getPropertyDefinitions().asRecord() ).toEqual( {
				[ existingProperty.name.toString() ]: existingProperty,
				[ addedProperty.name.toString() ]: addedProperty,
			} );
		} );

	} );

	describe( 'withRemovedPropertyDefinition', () => {

		it( 'removes a Property Definition', () => {
			const property1 = newTextProperty();
			const property2 = newNumberProperty();

			const originalSchema = newSchema( {
				properties: new PropertyDefinitionList( [ property1, property2 ] ),
			} );

			const updatedSchema = originalSchema.withRemovedPropertyDefinition( property1.name );

			expect( updatedSchema.getPropertyDefinitions().asRecord() ).toEqual( {
				[ property2.name.toString() ]: property2,
			} );
		} );

	} );

	describe( 'statementsFrom', () => {

		it( 'returns an empty StatementList when the Schema has no Property Definitions', () => {
			const schema = newSchema();

			const result = schema.statementsFrom( new StatementList( [] ) );

			expect( [ ...result ] ).toEqual( [] );
		} );

		it( 'creates an empty Statement for each Property Definition when no existing Statements are given', () => {
			const schema = newSchema( {
				properties: new PropertyDefinitionList( [
					newTextProperty( { name: 'foo' } ),
					newNumberProperty( { name: 'bar' } ),
				] ),
			} );

			const result = schema.statementsFrom( new StatementList( [] ) );

			expect( [ ...result ] ).toEqual( [
				new Statement( new PropertyName( 'foo' ), TextType.typeName, undefined ),
				new Statement( new PropertyName( 'bar' ), NumberType.typeName, undefined ),
			] );
		} );

		it( 'preserves an existing Statement whose Property is in the Schema', () => {
			const fooStatement = new Statement(
				new PropertyName( 'foo' ),
				TextType.typeName,
				newStringValue( 'hello' ),
			);
			const schema = newSchema( {
				properties: new PropertyDefinitionList( [
					newTextProperty( { name: 'before' } ),
					newTextProperty( { name: 'foo' } ),
					newTextProperty( { name: 'after' } ),
				] ),
			} );

			const result = schema.statementsFrom( new StatementList( [ fooStatement ] ) );

			expect( result.get( new PropertyName( 'foo' ) ) ).toBe( fooStatement );
		} );

		it( 'drops existing Statements whose Property is not in the Schema', () => {
			const orphan = new Statement(
				new PropertyName( 'orphan' ),
				TextType.typeName,
				newStringValue( 'x' ),
			);
			const schema = newSchema( {
				properties: new PropertyDefinitionList( [ newTextProperty( { name: 'foo' } ) ] ),
			} );

			const result = schema.statementsFrom( new StatementList( [ orphan ] ) );

			expect( result.has( new PropertyName( 'orphan' ) ) ).toBe( false );
			expect( result.has( new PropertyName( 'foo' ) ) ).toBe( true );
		} );

		it( 'orders Statements by Schema order, regardless of input order', () => {
			const aStatement = new Statement( new PropertyName( 'a' ), TextType.typeName, newStringValue( 'A' ) );
			const cStatement = new Statement( new PropertyName( 'c' ), TextType.typeName, newStringValue( 'C' ) );
			const schema = newSchema( {
				properties: new PropertyDefinitionList( [
					newTextProperty( { name: 'a' } ),
					newTextProperty( { name: 'b' } ),
					newTextProperty( { name: 'c' } ),
				] ),
			} );

			const result = schema.statementsFrom( new StatementList( [ cStatement, aStatement ] ) );

			expect( [ ...result ].map( ( s ) => s.propertyName.toString() ) ).toEqual( [ 'a', 'b', 'c' ] );
		} );

		it( 'preserves the writer\'s Property Type from the existing Statement, not the Schema\'s current type', () => {
			const existing = new Statement(
				new PropertyName( 'foo' ),
				TextType.typeName,
				newStringValue( 'hello' ),
			);
			const schema = newSchema( {
				properties: new PropertyDefinitionList( [ newNumberProperty( { name: 'foo' } ) ] ),
			} );

			const result = schema.statementsFrom( new StatementList( [ existing ] ) );

			expect( result.get( new PropertyName( 'foo' ) ).propertyType ).toBe( TextType.typeName );
		} );

	} );

	describe( 'blankStatements', () => {

		it( 'creates an empty Statement for each Property Definition', () => {
			const schema = newSchema( {
				properties: new PropertyDefinitionList( [
					newTextProperty( { name: 'foo' } ),
					newNumberProperty( { name: 'bar' } ),
				] ),
			} );

			const result = schema.blankStatements();

			expect( [ ...result ] ).toEqual( [
				new Statement( new PropertyName( 'foo' ), TextType.typeName, undefined ),
				new Statement( new PropertyName( 'bar' ), NumberType.typeName, undefined ),
			] );
		} );

	} );

} );
