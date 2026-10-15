import { describe, it, expect, beforeEach } from 'vitest';
import { identifyingSubjectName, subjectDisplayName } from '@/presentation/subjectDisplayName';
import { newSchema, newSubject } from '@/TestHelpers';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList';
import { newTextProperty, TextType } from '@/domain/propertyTypes/Text';
import { newNumberProperty, NumberType } from '@/domain/propertyTypes/Number';
import { newRelationProperty, RelationType } from '@/domain/propertyTypes/Relation';
import { StatementList } from '@/domain/StatementList';
import { Statement } from '@/domain/Statement';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newNumberValue, newRelation, newStringValue, RelationValue } from '@/domain/Value';
import { setupMwMock } from '../VueTestHelpers';
import type { HostSubject } from '@/domain/HostSubject';

const PICASSO: HostSubject = {
	id: 's1demo5sssssss3',
	schemaName: 'Person',
	displayName: 'Pablo Picasso',
	displayNameIsGenerated: false,
	hostSubject: null,
};

describe( 'subjectDisplayName', () => {

	beforeEach( () => {
		setupMwMock();
	} );

	it( 'shows a stored label as it was typed', () => {
		expect( subjectDisplayName( newSubject( { label: 'Rijksmuseum' } ) ) ).toBe( 'Rijksmuseum' );
	} );

	it( 'shows a Subject nobody named under its id, marked as a stand-in', () => {
		expect(
			subjectDisplayName( newSubject( {
				id: 's1demo5sssssss2',
				label: null,
				displayNameIsGenerated: true,
				schemaName: 'Attendance',
			} ) ),
		).toBe( '(s1demo5sssssss2)' );
	} );

	it( 'leaves a page-name fallback unmarked, since an editor wrote the page title', () => {
		expect(
			subjectDisplayName( newSubject( {
				label: null,
				displayName: 'Rijksmuseum',
				displayNameIsGenerated: false,
			} ) ),
		).toBe( 'Rijksmuseum' );
	} );

	it( 'names a Dependent Subject after its Schema and its Host Subject', () => {
		expect(
			subjectDisplayName( newSubject( {
				label: null,
				displayNameIsGenerated: true,
				schemaName: 'Birth',
				hostSubject: PICASSO,
			} ) ),
		).toBe( 'Birth of Pablo Picasso' );
	} );

	it( 'names a Dependent Subject of a Dependent Subject up the chain of Host Subjects', () => {
		expect(
			subjectDisplayName( newSubject( {
				label: null,
				displayNameIsGenerated: true,
				schemaName: 'Record',
				hostSubject: {
					id: 's1demo5sssssss4',
					schemaName: 'Birth',
					displayName: 'Birth',
					displayNameIsGenerated: true,
					hostSubject: PICASSO,
				},
			} ) ),
		).toBe( 'Record of Birth of Pablo Picasso' );
	} );

	it( 'names a Dependent Subject after a Host Subject nobody named by that Host Subject\'s id', () => {
		expect(
			subjectDisplayName( newSubject( {
				label: null,
				displayNameIsGenerated: true,
				schemaName: 'Birth',
				hostSubject: { ...PICASSO, displayName: 'Person', displayNameIsGenerated: true },
			} ) ),
		).toBe( 'Birth of (s1demo5sssssss3)' );
	} );

	/**
	 * The case a client comparing the display name against the Schema name would get wrong.
	 */
	it( 'leaves a stored label that happens to equal the Schema name unmarked', () => {
		expect(
			subjectDisplayName( newSubject( {
				label: 'Attendance',
				schemaName: 'Attendance',
				displayNameIsGenerated: false,
			} ) ),
		).toBe( 'Attendance' );
	} );

} );

describe( 'identifyingSubjectName', () => {

	const RIJKSMUSEUM: HostSubject = { ...PICASSO, schemaName: 'Museum', displayName: 'Rijksmuseum' };

	const attendanceSchema = newSchema( {
		title: 'Attendance',
		subjectsAreDependent: true,
		properties: new PropertyDefinitionList( [
			newRelationProperty( { name: 'Source', targetSchemas: [ 'Report' ] } ),
			newNumberProperty( { name: 'Year' } ),
			newTextProperty( { name: 'Visitors' } ),
		] ),
	} );

	const year = new Statement( new PropertyName( 'Year' ), NumberType.typeName, newNumberValue( 2024 ) );
	const visitors = new Statement( new PropertyName( 'Visitors' ), TextType.typeName, newStringValue( '2.7 million' ) );
	const source = new Statement(
		new PropertyName( 'Source' ),
		RelationType.typeName,
		new RelationValue( [ newRelation( undefined, 's1reportAAAAAA1' ) ] ),
	);

	function attendance( ...statements: Statement[] ): ReturnType<typeof newSubject> {
		return newSubject( {
			label: null,
			displayNameIsGenerated: true,
			schemaName: 'Attendance',
			hostSubject: RIJKSMUSEUM,
			statements: new StatementList( statements ),
		} );
	}

	beforeEach( () => {
		setupMwMock();
	} );

	it( 'adds the first value of a Dependent Subject, in Schema order, to its name', () => {
		expect( identifyingSubjectName( attendance( visitors, year ), attendanceSchema ) )
			.toBe( 'Attendance of Rijksmuseum (Year: 2024)' );
	} );

	it( 'passes over a value that reads as no text, such as a relation', () => {
		expect( identifyingSubjectName( attendance( source, visitors ), attendanceSchema ) )
			.toBe( 'Attendance of Rijksmuseum (Visitors: 2.7 million)' );
	} );

	it( 'names a Dependent Subject without a value by its name alone', () => {
		expect( identifyingSubjectName( attendance(), attendanceSchema ) ).toBe( 'Attendance of Rijksmuseum' );
	} );

	it( 'names a Dependent Subject by its name alone while its Schema is not at hand', () => {
		expect( identifyingSubjectName( attendance( year ), undefined ) ).toBe( 'Attendance of Rijksmuseum' );
	} );

	it( 'names a standalone Subject by its name alone', () => {
		const museum = newSubject( {
			label: 'Rijksmuseum',
			schemaName: 'Museum',
			statements: new StatementList( [ new Statement( new PropertyName( 'City' ), TextType.typeName, newStringValue( 'Amsterdam' ) ) ] ),
		} );

		expect( identifyingSubjectName( museum, newSchema( {
			title: 'Museum',
			properties: new PropertyDefinitionList( [ newTextProperty( { name: 'City' } ) ] ),
		} ) ) ).toBe( 'Rijksmuseum' );
	} );

} );
