import { describe, expect, it } from 'vitest';
import { newSchema, newSubject, DEFAULT_SUBJECT_ID } from '@neo/TestHelpers';
import { SubjectMap } from '@neo/domain/SubjectMap';
import { InMemorySubjectLookup } from '@neo/domain/SubjectLookup';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { StatementList } from '@neo/domain/StatementList';
import { PropertyDefinitionList } from '../PropertyDefinitionList';
import { createPropertyDefinitionFromJson } from '../PropertyDefinition';
import { ValueType } from '../Value';
import { TextFormat } from '../valueFormats/Text';
import { RelationFormat } from '../valueFormats/Relation';

describe( 'Subject', () => {

	it( 'should be constructable via newSubject', () => {
		const subject = newSubject( {
			label: 'I am a tomato',
			schemaId: 'Tomato'
		} );

		expect( subject.getId().text ).toBe( DEFAULT_SUBJECT_ID );
		expect( subject.getLabel() ).toBe( 'I am a tomato' );
		expect( subject.getSchemaName() ).toBe( 'Tomato' );
		expect( subject.getPageIdentifiers().getPageName() ).toBe( 'TestSubjectPage' );
	} );

	it( 'should store page identifiers', () => {
		const identifiers = new PageIdentifiers( 123, 'TestPage' );

		const subject = newSubject( {
			pageIdentifiers: identifiers
		} );

		expect( subject.getPageIdentifiers() ).toEqual( identifiers );
	} );

	describe( 'getReferencedSubjects', () => {

		it( 'should return empty SubjectMap when there are no statements', async () => {
			const subject = newSubject();
			const lookup = new InMemorySubjectLookup( [] );

			expect( await subject.getReferencedSubjects( lookup ) ).toEqual( new SubjectMap() );
		} );

		it( 'should return a SubjectMap with referenced Subjects', async () => {
			const subject1 = newSubject( { id: 's11111111111111' } );
			const subject2 = newSubject( { id: 's11111111111112' } );
			const subject3 = newSubject( { id: 's11111111111113' } );
			const lookup = new InMemorySubjectLookup( [ subject1, subject2, subject3 ] );

			const subject = newSubject( {
				id: DEFAULT_SUBJECT_ID,
				statements: StatementList.fromJsonValues(
					{
						Property1: {
							value: [ 'foo' ],
							format: TextFormat.formatName
						},
						Property2: {
							value: [ { target: 's11111111111111' } ],
							format: RelationFormat.formatName
						},
						Property3: {
							value: [ { target: 's11111111111112' }, { target: 's11111111111113' } ],
							format: RelationFormat.formatName
						},
						Property4: {
							value: [ 'bar' ],
							format: TextFormat.formatName
						}
					},
					newSchema( {
						properties: new PropertyDefinitionList( [
							createPropertyDefinitionFromJson(
								'Property1',
								{
									type: ValueType.String,
									format: TextFormat.formatName
								}
							),
							createPropertyDefinitionFromJson(
								'Property2',
								{
									type: ValueType.Relation,
									format: RelationFormat.formatName
								}
							),
							createPropertyDefinitionFromJson(
								'Property3',
								{
									type: ValueType.String,
									format: RelationFormat.formatName
								}
							),
							createPropertyDefinitionFromJson(
								'Property4',
								{
									type: ValueType.String,
									format: TextFormat.formatName
								}
							)
						] )
					} )
				)
			} );

			const subjectMap = await subject.getReferencedSubjects( lookup );

			expect( subjectMap ).toEqual( new SubjectMap( subject1, subject2, subject3 ) );
		} );

		it( 'should return a SubjectMap with referenced Subjects excluding missing Subjects', async () => {
			const referencedSubject = newSubject( { id: 's11111111111111' } );
			const lookup = new InMemorySubjectLookup( [ referencedSubject ] );

			const subject = newSubject( {
				id: DEFAULT_SUBJECT_ID,
				statements: StatementList.fromJsonValues(
					{
						Property1: {
							value: [ 'foo' ],
							format: TextFormat.formatName
						},
						Property2: {
							value: [ { target: 's11111111111118' } ],
							format: RelationFormat.formatName
						},
						Property3: {
							value: [ { target: 's11111111111111' }, { target: 's11111111111119' } ],
							format: RelationFormat.formatName
						},
						Property4: {
							value: [ 'bar' ],
							format: TextFormat.formatName
						}
					},
					newSchema( {
						properties: new PropertyDefinitionList( [
							createPropertyDefinitionFromJson(
								'Property1',
								{
									type: ValueType.String,
									format: TextFormat.formatName
								}
							),
							createPropertyDefinitionFromJson(
								'Property2',
								{
									type: ValueType.Relation,
									format: RelationFormat.formatName
								}
							),
							createPropertyDefinitionFromJson(
								'Property3',
								{
									type: ValueType.String,
									format: RelationFormat.formatName
								}
							),
							createPropertyDefinitionFromJson(
								'Property4',
								{
									type: ValueType.String,
									format: TextFormat.formatName
								}
							)
						] )
					} )
				)
			} );

			const subjectMap = await subject.getReferencedSubjects( lookup );

			expect( subjectMap ).toEqual( new SubjectMap( referencedSubject ) );
		} );

	} );

} );
