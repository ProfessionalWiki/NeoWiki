import { describe, expect, it } from 'vitest';
import { DEFAULT_SUBJECT_ID, newSubject } from '@/TestHelpers';
import { SubjectMap } from '@/domain/SubjectMap';
import { InMemorySubjectLookup } from '@/domain/SubjectLookup';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { StatementList } from '@/domain/StatementList';
import { Neo } from '@/Neo';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue } from '@/domain/Value';
import { Statement } from '@/domain/Statement';
import { TextType } from '@/domain/propertyTypes/Text';
import { RelationType } from '@/domain/propertyTypes/Relation';

describe( 'Subject', () => {

	it( 'should be constructable via newSubject', () => {
		const subject = newSubject( {
			label: 'I am a tomato',
			schemaName: 'Tomato',
		} );

		expect( subject.getId().text ).toBe( DEFAULT_SUBJECT_ID );
		expect( subject.getLabel() ).toBe( 'I am a tomato' );
		expect( subject.getSchemaName() ).toBe( 'Tomato' );
		expect( subject.getPageIdentifiers().getPageName() ).toBe( 'TestSubjectPage' );
	} );

	it( 'should store page identifiers', () => {
		const identifiers = new PageIdentifiers( 123, 'TestPage' );

		const subject = newSubject( {
			pageIdentifiers: identifiers,
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
							propertyType: TextType.typeName,
						},
						Property2: {
							value: [ { target: 's11111111111111' } ],
							propertyType: RelationType.typeName,
						},
						Property3: {
							value: [ { target: 's11111111111112' }, { target: 's11111111111113' } ],
							propertyType: RelationType.typeName,
						},
						Property4: {
							value: [ 'bar' ],
							propertyType: TextType.typeName,
						},
					},
				),
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
							propertyType: TextType.typeName,
						},
						Property2: {
							value: [ { target: 's11111111111118' } ],
							propertyType: RelationType.typeName,
						},
						Property3: {
							value: [ { target: 's11111111111111' }, { target: 's11111111111119' } ],
							propertyType: RelationType.typeName,
						},
						Property4: {
							value: [ 'bar' ],
							propertyType: TextType.typeName,
						},
					},
				),
			} );

			const subjectMap = await subject.getReferencedSubjects( lookup );

			expect( subjectMap ).toEqual( new SubjectMap( referencedSubject ) );
		} );

	} );

	describe( 'getDisplayName', () => {
		it( 'is the stored label when the Subject has one', () => {
			expect( newSubject( { label: 'I am a tomato' } ).getDisplayName() ).toBe( 'I am a tomato' );
		} );

		it( 'is the name the server derived when the Subject has no label', () => {
			const subject = newSubject( { label: null, displayName: 'TestSubjectPage' } );

			expect( subject.getLabel() ).toBeNull();
			expect( subject.getDisplayName() ).toBe( 'TestSubjectPage' );
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

		it( 'displays the label it was given', () => {
			expect( newSubject().withLabel( 'Updated Label' ).getDisplayName() ).toBe( 'Updated Label' );
		} );

		it( 'keeps the previous display name when the label is cleared, since only the server can derive a new one', () => {
			const original = newSubject( { label: 'Acme Anvil' } );

			const cleared = original.withLabel( null );

			expect( cleared.getLabel() ).toBeNull();
			expect( cleared.getDisplayName() ).toBe( 'Acme Anvil' );
		} );
	} );

	describe( 'withStatements', () => {
		it( 'returns a new Subject with the updated statements', () => {
			const originalSubject = newSubject();

			const newStatements = new StatementList( [
				{
					propertyName: new PropertyName( 'testProperty' ),
					propertyType: TextType.typeName,
					value: newStringValue( 'Test Value' ),
				} as Statement,
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
