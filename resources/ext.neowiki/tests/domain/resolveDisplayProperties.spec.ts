import { describe, it, expect } from 'vitest';
import { resolveDisplayProperties } from '@/domain/resolveDisplayProperties';
import { newSubject, newSchema } from '@/TestHelpers';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { StatementList } from '@/domain/StatementList';
import { Statement } from '@/domain/Statement';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newNumberProperty, type NumberProperty } from '@/domain/propertyTypes/Number';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newNumberValue, newStringValue } from '@/domain/Value';
import { View } from '@/domain/View';

function newView( displayRules: { property: string; displayAttributes?: Record<string, unknown> }[] ): View {
	return new View(
		'TestView',
		'TestSchema',
		'infobox',
		'',
		displayRules.map( ( rule ) => ( {
			property: new PropertyName( rule.property ),
			displayAttributes: rule.displayAttributes,
		} ) ),
		{},
	);
}

describe( 'resolveDisplayProperties', () => {

	it( 'returns all non-empty properties in schema order when no view is given', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Name' } ),
				newNumberProperty( { name: 'Age' } ),
				newTextProperty( { name: 'City' } ),
			] ),
		} );
		const subject = newSubject( {
			statements: new StatementList( [
				new Statement( new PropertyName( 'Name' ), 'text', newStringValue( 'Alice' ) ),
				new Statement( new PropertyName( 'Age' ), 'number', newNumberValue( 30 ) ),
				new Statement( new PropertyName( 'City' ), 'text', newStringValue( 'Berlin' ) ),
			] ),
		} );

		const result = resolveDisplayProperties( schema, subject );

		expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'Name', 'Age', 'City' ] );
		expect( result[ 0 ].value ).toEqual( newStringValue( 'Alice' ) );
		expect( result[ 1 ].value ).toEqual( newNumberValue( 30 ) );
		expect( result[ 2 ].value ).toEqual( newStringValue( 'Berlin' ) );
	} );

	it( 'filters and orders properties according to display rules', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Name' } ),
				newNumberProperty( { name: 'Age' } ),
				newTextProperty( { name: 'City' } ),
			] ),
		} );
		const subject = newSubject( {
			statements: new StatementList( [
				new Statement( new PropertyName( 'Name' ), 'text', newStringValue( 'Alice' ) ),
				new Statement( new PropertyName( 'Age' ), 'number', newNumberValue( 30 ) ),
				new Statement( new PropertyName( 'City' ), 'text', newStringValue( 'Berlin' ) ),
			] ),
		} );
		const view = newView( [
			{ property: 'City' },
			{ property: 'Name' },
		] );

		const result = resolveDisplayProperties( schema, subject, view );

		expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'City', 'Name' ] );
		expect( result[ 0 ].value ).toEqual( newStringValue( 'Berlin' ) );
		expect( result[ 1 ].value ).toEqual( newStringValue( 'Alice' ) );
	} );

	it( 'merges display attributes from display rule onto property definition', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newNumberProperty( { name: 'Revenue', precision: 2 } ),
			] ),
		} );
		const subject = newSubject( {
			statements: new StatementList( [
				new Statement( new PropertyName( 'Revenue' ), 'number', newNumberValue( 1234567 ) ),
			] ),
		} );
		const view = newView( [
			{ property: 'Revenue', displayAttributes: { precision: 0 } },
		] );

		const result = resolveDisplayProperties( schema, subject, view );

		expect( result ).toHaveLength( 1 );
		expect( ( result[ 0 ].propertyDefinition as NumberProperty ).precision ).toBe( 0 );
	} );

	it( 'returns all non-empty properties in schema order when view has empty display rules', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Name' } ),
				newNumberProperty( { name: 'Age' } ),
			] ),
		} );
		const subject = newSubject( {
			statements: new StatementList( [
				new Statement( new PropertyName( 'Name' ), 'text', newStringValue( 'Alice' ) ),
				new Statement( new PropertyName( 'Age' ), 'number', newNumberValue( 30 ) ),
			] ),
		} );
		const view = newView( [] );

		const result = resolveDisplayProperties( schema, subject, view );

		expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'Name', 'Age' ] );
	} );

	it( 'skips display rules referencing unknown properties', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Name' } ),
			] ),
		} );
		const subject = newSubject( {
			statements: new StatementList( [
				new Statement( new PropertyName( 'Name' ), 'text', newStringValue( 'Alice' ) ),
			] ),
		} );
		const view = newView( [
			{ property: 'Name' },
			{ property: 'NonExistentProperty' },
		] );

		const result = resolveDisplayProperties( schema, subject, view );

		expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'Name' ] );
	} );

	it( 'falls back to all properties when view schema does not match subject schema', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Name' } ),
				newNumberProperty( { name: 'Age' } ),
			] ),
		} );
		const subject = newSubject( {
			schemaName: 'DifferentSchema',
			statements: new StatementList( [
				new Statement( new PropertyName( 'Name' ), 'text', newStringValue( 'Alice' ) ),
				new Statement( new PropertyName( 'Age' ), 'number', newNumberValue( 30 ) ),
			] ),
		} );
		const view = newView( [
			{ property: 'Name' },
		] );

		const result = resolveDisplayProperties( schema, subject, view );

		expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'Name', 'Age' ] );
	} );

	it( 'skips display rules for properties the subject has no statement for', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Name' } ),
				newNumberProperty( { name: 'Age' } ),
			] ),
		} );
		const subject = newSubject( {
			statements: new StatementList( [
				new Statement( new PropertyName( 'Name' ), 'text', newStringValue( 'Alice' ) ),
			] ),
		} );
		const view = newView( [
			{ property: 'Name' },
			{ property: 'Age' },
		] );

		const result = resolveDisplayProperties( schema, subject, view );

		expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'Name' ] );
	} );

	it( 'skips properties with no value on the subject', () => {
		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Name' } ),
				newNumberProperty( { name: 'Age' } ),
				newTextProperty( { name: 'City' } ),
			] ),
		} );
		const subject = newSubject( {
			statements: new StatementList( [
				new Statement( new PropertyName( 'Name' ), 'text', newStringValue( 'Alice' ) ),
				new Statement( new PropertyName( 'Age' ), 'number', undefined ),
				new Statement( new PropertyName( 'City' ), 'text', newStringValue( 'Berlin' ) ),
			] ),
		} );
		const view = newView( [
			{ property: 'Name' },
			{ property: 'Age' },
			{ property: 'City' },
		] );

		const result = resolveDisplayProperties( schema, subject, view );

		expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'Name', 'City' ] );
	} );

} );
