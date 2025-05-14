import { describe, expect, it } from 'vitest';
import { DEFAULT_SUBJECT_ID, newSubject } from '@neo/TestHelpers';
import { SubjectMap } from '@neo/domain/SubjectMap';
import { InMemorySubjectLookup } from '@neo/domain/SubjectLookup';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers';
import { StatementList } from '@neo/domain/StatementList';
import { Neo } from '@neo/Neo';
import { PropertyName } from '@neo/domain/PropertyDefinition';
import { newStringValue } from '@neo/domain/Value';
import { Statement } from '@neo/domain/Statement';
import { TextType } from '@neo/domain/propertyTypes/Text';
import { RelationType } from '@neo/domain/propertyTypes/Relation';

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
				statements: Neo.getInstance().getSubjectDeserializer().deserializeStatements(
					{
						Property1: {
							value: [ 'foo' ],
							type: TextType.typeName
						},
						Property2: {
							value: [ { target: 's11111111111111' } ],
							type: RelationType.typeName
						},
						Property3: {
							value: [ { target: 's11111111111112' }, { target: 's11111111111113' } ],
							type: RelationType.typeName
						},
						Property4: {
							value: [ 'bar' ],
							type: TextType.typeName
						}
					}
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
				statements: Neo.getInstance().getSubjectDeserializer().deserializeStatements(
					{
						Property1: {
							value: [ 'foo' ],
							type: TextType.typeName
						},
						Property2: {
							value: [ { target: 's11111111111118' } ],
							type: RelationType.typeName
						},
						Property3: {
							value: [ { target: 's11111111111111' }, { target: 's11111111111119' } ],
							type: RelationType.typeName
						},
						Property4: {
							value: [ 'bar' ],
							type: TextType.typeName
						}
					}
				)
			} );

			const subjectMap = await subject.getReferencedSubjects( lookup );

			expect( subjectMap ).toEqual( new SubjectMap( referencedSubject ) );
		} );

	} );

	describe( 'withLabel', () => {
		it( 'returns a new Subject with the updated label', () => {
			const originalSubject = newSubject();

			const updatedSubject = originalSubject.withLabel( 'Updated Label' );

			expect( updatedSubject.getLabel() ).toBe( 'Updated Label' );
			expect( updatedSubject.getSchemaName() ).toBe( originalSubject.getSchemaName() );
			expect( updatedSubject.getStatements() ).toEqual( originalSubject.getStatements() );
			expect( updatedSubject ).not.toBe( originalSubject );
		} );
	} );

	describe( 'withStatements', () => {
		it( 'returns a new Subject with the updated statements', () => {
			const originalSubject = newSubject();

			const newStatements = new StatementList( [
				{
					propertyName: new PropertyName( 'testProperty' ),
					propertyType: TextType.typeName,
					value: newStringValue( 'Test Value' )
				} as Statement
			] );

			const updatedSubject = originalSubject.withStatements( newStatements );

			expect( updatedSubject.getLabel() ).toBe( originalSubject.getLabel() );
			expect( updatedSubject.getSchemaName() ).toBe( originalSubject.getSchemaName() );
			expect( updatedSubject.getStatements() ).toEqual( newStatements );
			expect( updatedSubject ).not.toBe( originalSubject );
		} );
	} );

	describe( 'withSchemaName', () => {
		it( 'returns a new Subject with the updated schema name', () => {
			const originalSubject = newSubject();

			const updatedSubject = originalSubject.withSchemaName( 'NewSchema' );

			expect( updatedSubject.getLabel() ).toBe( originalSubject.getLabel() );
			expect( updatedSubject.getSchemaName() ).toBe( 'NewSchema' );
			expect( updatedSubject.getStatements() ).toEqual( originalSubject.getStatements() );
			expect( updatedSubject ).not.toBe( originalSubject );
		} );
	} );

} );
