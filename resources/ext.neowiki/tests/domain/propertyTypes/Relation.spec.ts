import { describe, expect, it } from 'vitest';
import { newRelationProperty, RelationProperty, RelationType } from '@/domain/propertyTypes/Relation';
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import { newRelation, RelationValue } from '@/domain/Value';

describe( 'RelationType', () => {

	it( 'has no display attributes', () => {
		expect( new RelationType().getDisplayAttributeNames() ).toEqual( [] );
	} );

	describe( 'createPropertyDefinitionFromJson', () => {

		const relationPropertyNamed = ( name: string, json: object ): RelationProperty =>
			new RelationType().createPropertyDefinitionFromJson(
				{
					name: new PropertyName( name ),
					type: RelationType.typeName,
					description: '',
					required: false,
					default: undefined,
				} as PropertyDefinition,
				{ targetSchema: 'Artist', ...json },
			);

		it( 'keeps the stored relation type', () => {
			expect( relationPropertyNamed( 'Creator', { relation: 'Created by' } ).relation )
				.toBe( 'Created by' );
		} );

		it( 'names the relation type after the property when the JSON has none', () => {
			expect( relationPropertyNamed( 'Creator', {} ).relation ).toBe( 'Creator' );
		} );

		// A Schema the wiki stored never has one: an empty relation type fails content validation.
		it( 'names the relation type after the property when the JSON has an empty one', () => {
			expect( relationPropertyNamed( 'Creator', { relation: '' } ).relation ).toBe( 'Creator' );
		} );

	} );

} );

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
			name: 'CustomRelation',
		} );

		expect( property.name ).toEqual( new PropertyName( 'CustomRelation' ) );
	} );

	it( 'accepts PropertyName instance for name', () => {
		const propertyName = new PropertyName( 'customRelation' );
		const property = newRelationProperty( {
			name: propertyName,
		} );

		expect( property.name ).toBe( propertyName );
	} );

	it( 'creates property with all optional fields', () => {
		const relation = new RelationValue( [
			newRelation( 'r11111111111111', 's11111111111111' ),
		] );

		const property = newRelationProperty( {
			name: 'FullRelation',
			description: 'A relation property',
			required: true,
			default: relation,
			relation: 'CustomRelation',
			targetSchema: 'CustomSchema',
			multiple: true,
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
			relation: 'CustomRelation',
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
