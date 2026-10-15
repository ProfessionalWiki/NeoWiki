import { describe, expect, it } from 'vitest';
import {
	newRelationProperty,
	propertiesTargetingDependentSubjects,
	RelationType,
	targetsDependentSubjects,
} from '@/domain/propertyTypes/Relation';
import { PropertyName } from '@/domain/PropertyDefinition';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newRelation, RelationValue } from '@/domain/Value';
import { newSchema } from '@/TestHelpers';

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
		expect( property.targetSchemas ).toEqual( [ 'MyTargetSchema' ] );
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
			targetSchemas: [ 'CustomSchema', 'OtherSchema' ],
			multiple: true,
		} );

		expect( property.name ).toEqual( new PropertyName( 'FullRelation' ) );
		expect( property.type ).toBe( RelationType.typeName );
		expect( property.description ).toBe( 'A relation property' );
		expect( property.required ).toBe( true );
		expect( property.default ).toStrictEqual( relation );
		expect( property.targetSchemas ).toEqual( [ 'CustomSchema', 'OtherSchema' ] );
		expect( property.multiple ).toBe( true );
	} );

	it( 'creates property with some optional fields', () => {
		const property = newRelationProperty( {
			name: 'PartialRelation',
			description: 'A partial relation property',
		} );

		expect( property.name ).toEqual( new PropertyName( 'PartialRelation' ) );
		expect( property.type ).toBe( RelationType.typeName );
		expect( property.description ).toBe( 'A partial relation property' );
		expect( property.required ).toBe( false );
		expect( property.default ).toBeUndefined();
		expect( property.targetSchemas ).toEqual( [ 'MyTargetSchema' ] );
		expect( property.multiple ).toBe( false );
	} );
} );

describe( 'targetsDependentSubjects', () => {

	const answers: Record<string, boolean> = { Birth: true, Place: false };

	function subjectsAreDependent( schemaName: string ): boolean | undefined {
		return answers[ schemaName ];
	}

	it( 'answers as the first target Schema whose answer is known', () => {
		expect( targetsDependentSubjects( [ 'Missing', 'Birth', 'Place' ], subjectsAreDependent ) ).toBe( true );
	} );

	it( 'answers for standalone targets as for dependent ones', () => {
		expect( targetsDependentSubjects( [ 'Place', 'Birth' ], subjectsAreDependent ) ).toBe( false );
	} );

	it( 'is null when no target Schema has a known answer', () => {
		expect( targetsDependentSubjects( [ 'Missing', 'Gone' ], subjectsAreDependent ) ).toBeNull();
	} );

} );

describe( 'propertiesTargetingDependentSubjects', () => {

	const answers: Record<string, boolean> = { Attendance: true, City: false, Company: false };

	function subjectsAreDependent( schemaName: string ): boolean | undefined {
		return answers[ schemaName ];
	}

	function namesOf( schema: ReturnType<typeof newSchema> ): string[] {
		return propertiesTargetingDependentSubjects( schema, subjectsAreDependent ).map( ( property ) => property.name.toString() );
	}

	it( 'names the relation properties whose target Schemas have Dependent Subjects', () => {
		const museum = newSchema( {
			title: 'Museum',
			properties: new PropertyDefinitionList( [
				newTextProperty( { name: 'Description' } ),
				newRelationProperty( { name: 'City', targetSchemas: [ 'City' ] } ),
				newRelationProperty( { name: 'Attendance figures', targetSchemas: [ 'Attendance' ] } ),
				newRelationProperty( { name: 'Owner', targetSchemas: [ 'Company' ] } ),
			] ),
		} );

		expect( namesOf( museum ) ).toEqual( [ 'Attendance figures' ] );
	} );

	it( 'leaves out a relation property none of whose target Schemas is known', () => {
		const museum = newSchema( {
			title: 'Museum',
			properties: new PropertyDefinitionList( [
				newRelationProperty( { name: 'Visits', targetSchemas: [ 'Visit' ] } ),
			] ),
		} );

		expect( namesOf( museum ) ).toEqual( [] );
	} );

} );
