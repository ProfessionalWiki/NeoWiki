import { describe, it, expect } from 'vitest';
import { resolveDisplayProperties } from '@/domain/resolveDisplayProperties';
import { DEFAULT_SUBJECT_ID, newSubject, newSchema } from '@/TestHelpers';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { StatementList } from '@/domain/StatementList';
import { Statement } from '@/domain/Statement';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newNumberProperty, type NumberProperty } from '@/domain/propertyTypes/Number';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newRelationProperty, RelationType } from '@/domain/propertyTypes/Relation';
import { newNumberValue, newRelation, newStringValue, RelationValue } from '@/domain/Value';
import { Layout } from '@/domain/Layout';
import type { Subject } from '@/domain/Subject';
import type { SubjectId } from '@/domain/SubjectId';

function newLayout( displayRules: { property: string; displayAttributes?: Record<string, unknown> }[] ): Layout {
	return new Layout(
		'TestLayout',
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

function findNoSubject(): Subject | undefined {
	return undefined;
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

		const result = resolveDisplayProperties( schema, subject, undefined, findNoSubject );

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
		const layout = newLayout( [
			{ property: 'City' },
			{ property: 'Name' },
		] );

		const result = resolveDisplayProperties( schema, subject, layout, findNoSubject );

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
		const layout = newLayout( [
			{ property: 'Revenue', displayAttributes: { precision: 0 } },
		] );

		const result = resolveDisplayProperties( schema, subject, layout, findNoSubject );

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
		const layout = newLayout( [] );

		const result = resolveDisplayProperties( schema, subject, layout, findNoSubject );

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
		const layout = newLayout( [
			{ property: 'Name' },
			{ property: 'NonExistentProperty' },
		] );

		const result = resolveDisplayProperties( schema, subject, layout, findNoSubject );

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
		const layout = newLayout( [
			{ property: 'Name' },
		] );

		const result = resolveDisplayProperties( schema, subject, layout, findNoSubject );

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
		const layout = newLayout( [
			{ property: 'Name' },
			{ property: 'Age' },
		] );

		const result = resolveDisplayProperties( schema, subject, layout, findNoSubject );

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
		const layout = newLayout( [
			{ property: 'Name' },
			{ property: 'Age' },
			{ property: 'City' },
		] );

		const result = resolveDisplayProperties( schema, subject, layout, findNoSubject );

		expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'Name', 'City' ] );
	} );

	describe( 'a relation property that points at Dependent Subjects', () => {

		const birthId = 's1birthAAAAAAA1';
		const friendId = 's1friendAAAAAA1';

		const schema = newSchema( {
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Name' } ),
				newRelationProperty( { name: 'Birth', targetSchemas: [ 'Birth' ] } ),
				newRelationProperty( { name: 'Friend', targetSchemas: [ 'Person' ] } ),
			] ),
		} );

		const subject = newSubject( {
			statements: new StatementList( [
				new Statement( new PropertyName( 'Name' ), 'text', newStringValue( 'Alice' ) ),
				new Statement( new PropertyName( 'Birth' ), RelationType.typeName, new RelationValue( [ newRelation( undefined, birthId ) ] ) ),
				new Statement( new PropertyName( 'Friend' ), RelationType.typeName, new RelationValue( [ newRelation( undefined, friendId ) ] ) ),
			] ),
		} );

		const relationTargets = [
			newSubject( {
				id: birthId,
				label: null,
				schemaName: 'Birth',
				hostSubject: {
					id: DEFAULT_SUBJECT_ID,
					schemaName: 'TestSchema',
					displayName: 'Alice',
					displayNameIsGenerated: false,
					hostSubject: null,
				},
			} ),
			newSubject( { id: friendId, label: 'Bob', schemaName: 'Person' } ),
		];

		function findRelationTarget( id: SubjectId ): Subject | undefined {
			return relationTargets.find( ( target ) => target.getId().text === id.text );
		}

		it( 'is left out without a Layout, while the other properties stay', () => {
			const result = resolveDisplayProperties( schema, subject, undefined, findRelationTarget );

			expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'Name', 'Friend' ] );
		} );

		it( 'keeps a relation property whose target was not read', () => {
			const birthOnly = ( id: SubjectId ): Subject | undefined => id.text === birthId ? relationTargets[ 0 ] : undefined;

			const result = resolveDisplayProperties( schema, subject, undefined, birthOnly );

			expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'Name', 'Friend' ] );
		} );

		it( 'is shown where a Display Rule of the Layout names it', () => {
			const layout = newLayout( [
				{ property: 'Birth' },
				{ property: 'Name' },
			] );

			const result = resolveDisplayProperties( schema, subject, layout, findRelationTarget );

			expect( result.map( ( r ) => r.propertyDefinition.name.toString() ) ).toEqual( [ 'Birth', 'Name' ] );
		} );

	} );

} );
