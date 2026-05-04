import { describe, expect, it } from 'vitest';
import { newRelationProperty, RelationType } from '@/domain/propertyTypes/Relation';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newRelation, RelationValue, Relation } from '@/domain/Value';

describe( 'RelationType', () => {

	it( 'has no display attributes', () => {
		expect( new RelationType().getDisplayAttributeNames() ).toEqual( [] );
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

describe( 'RelationType.validate', () => {

	const type = new RelationType();

	it( 'returns no errors when not required and value is undefined', () => {
		const property = newRelationProperty();
		expect( type.validate( undefined, property ) ).toEqual( [] );
	} );

	it( 'returns required error when required and value is undefined', () => {
		const property = newRelationProperty( { required: true } );
		expect( type.validate( undefined, property ) ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns required error when required and value has empty relations', () => {
		const property = newRelationProperty( { required: true } );
		expect( type.validate( new RelationValue( [] ), property ) ).toEqual( [ { code: 'required' } ] );
	} );

	it( 'returns no errors when relations all have valid SubjectIds', () => {
		const property = newRelationProperty();
		const value = new RelationValue( [
			newRelation( undefined, 's11111111111111' ),
			newRelation( undefined, 's22222222222222' ),
		] );
		expect( type.validate( value, property ) ).toEqual( [] );
	} );

	it( 'returns one invalid-subject-id error per malformed target', () => {
		const property = newRelationProperty();
		// Construct Relation directly with mock SubjectId objects to bypass validation
		// in the SubjectId constructor
		const invalidSubjectIdMock1 = { text: 'not-a-valid-id' };
		const invalidSubjectIdMock2 = { text: 'also-bad' };
		const value = new RelationValue( [
			new Relation( undefined, invalidSubjectIdMock1 as any ),
			new Relation( undefined, invalidSubjectIdMock2 as any ),
		] );
		expect( type.validate( value, property ) ).toEqual( [
			{ code: 'invalid-subject-id', args: [ 'not-a-valid-id' ] },
			{ code: 'invalid-subject-id', args: [ 'also-bad' ] },
		] );
	} );

} );
