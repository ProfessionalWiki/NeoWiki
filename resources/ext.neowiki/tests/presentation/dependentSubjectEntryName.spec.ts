import { describe, expect, it } from 'vitest';
import { dependentSubjectEntryName } from '@/presentation/dependentSubjectEntryName';
import { Statement } from '@/domain/Statement';
import { StatementList } from '@/domain/StatementList';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newNumberValue, newRelation, newStringValue, RelationValue } from '@/domain/Value';
import { NumberType } from '@/domain/propertyTypes/Number';
import { TextType } from '@/domain/propertyTypes/Text';
import { RelationType } from '@/domain/propertyTypes/Relation';
import { newNumberProperty } from '@/domain/propertyTypes/Number';
import { newTextProperty } from '@/domain/propertyTypes/Text';
import { newRelationProperty } from '@/domain/propertyTypes/Relation';
import { newSelectProperty, SelectType } from '@/domain/propertyTypes/Select';
import { newSchema, newSubject } from '@/TestHelpers';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';

const attendance = newSchema( {
	title: 'Annual attendance',
	properties: new PropertyDefinitionList( [
		newRelationProperty( { name: 'Museum' } ),
		newNumberProperty( { name: 'Year' } ),
		newTextProperty( { name: 'Source' } ),
	] ),
} );

function figure( ...statements: Statement[] ): ReturnType<typeof newSubject> {
	return newSubject( { label: null, schemaName: 'Annual attendance', statements: new StatementList( statements ) } );
}

describe( 'dependentSubjectEntryName', () => {

	it( 'adds the first field, in the Schema\'s order, that reads as text', () => {
		const name = dependentSubjectEntryName( 'Attendance figures', figure(
			new Statement( new PropertyName( 'Source' ), TextType.typeName, newStringValue( 'rijksmuseum.nl' ) ),
			new Statement( new PropertyName( 'Museum' ), RelationType.typeName, new RelationValue( [ newRelation( undefined, 's11111111111111' ) ] ) ),
			new Statement( new PropertyName( 'Year' ), NumberType.typeName, newNumberValue( 2023 ) ),
		), attendance );

		expect( name ).toBe( 'Attendance figures, 2023' );
	} );

	it( 'reads a field the way its property type shows it', () => {
		const statuses = newSchema( {
			title: 'Status',
			properties: new PropertyDefinitionList( [ newSelectProperty( {
				name: 'Status',
				options: [ { id: 'opt1', label: 'Active' } ],
			} ) ] ),
		} );

		const name = dependentSubjectEntryName( 'Statuses', figure(
			new Statement( new PropertyName( 'Status' ), SelectType.typeName, newStringValue( 'opt1' ) ),
		), statuses );

		expect( name ).toBe( 'Statuses, Active' );
	} );

	it( 'is the property alone for a Subject with no field that reads as text', () => {
		expect( dependentSubjectEntryName( 'Attendance figures', figure(), attendance ) ).toBe( 'Attendance figures' );
	} );

} );
