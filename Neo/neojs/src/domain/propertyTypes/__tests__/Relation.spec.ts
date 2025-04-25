import { describe, expect, it } from 'vitest';
import { newRelationProperty, RelationType } from '@neo/domain/propertyTypes/Relation';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newRelation, Relation, RelationValue } from '@neo/domain/Value';

describe( 'newRelationProperty', () => {
	it( 'creates property with default values when no attributes provided', () => {
		const property = newRelationProperty();

		expect( property.name ).toEqual( new PropertyName( 'Relation' ) );
		expect( property.type ).toBe( RelationType.typeName );
		expect( property.description ).toBe( '' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.relation ).toBe( 'MyRelation' );
		expect( property.targetSchema ).toBe( 'MyTargetSchema' );
		expect( property.multiple ).toBe( false );
	} );

	it( 'creates property with custom name as string', () => {
		const property = newRelationProperty( {
			name: 'CustomRelation'
		} );

		expect( property.name ).toEqual( new PropertyName( 'CustomRelation' ) );
	} );

	it( 'accepts PropertyName instance for name', () => {
		const propertyName = new PropertyName( 'customRelation' );
		const property = newRelationProperty( {
			name: propertyName
		} );

		expect( property.name ).toBe( propertyName );
	} );

	it( 'creates property with all optional fields', () => {
		const relation = new RelationValue( [
			newRelation( 'r11111111111111', 's11111111111111' )
		] );

		const property = newRelationProperty( {
			name: 'FullRelation',
			description: 'A relation property',
			required: true,
			default: relation,
			relation: 'CustomRelation',
			targetSchema: 'CustomSchema',
			multiple: true
		} );

		expect( property.name ).toEqual( new PropertyName( 'FullRelation' ) );
		expect( property.type ).toBe( RelationType.typeName );
		expect( property.description ).toBe( 'A relation property' );
		expect( property.required ).toBe( true );
		expect( property.default ).toStrictEqual( relation );
		expect( property.relation ).toBe( 'CustomRelation' );
		expect( property.targetSchema ).toBe( 'CustomSchema' );
		expect( property.multiple ).toBe( true );
	} );

	it( 'creates property with some optional fields', () => {
		const property = newRelationProperty( {
			name: 'PartialRelation',
			description: 'A partial relation property',
			relation: 'CustomRelation'
		} );

		expect( property.name ).toEqual( new PropertyName( 'PartialRelation' ) );
		expect( property.type ).toBe( RelationType.typeName );
		expect( property.description ).toBe( 'A partial relation property' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.relation ).toBe( 'CustomRelation' );
		expect( property.targetSchema ).toBe( 'MyTargetSchema' );
		expect( property.multiple ).toBe( false );
	} );
} );
