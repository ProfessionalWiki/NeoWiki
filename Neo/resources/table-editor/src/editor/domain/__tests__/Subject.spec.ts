import { describe, expect, it } from 'vitest';
import { newSubject, ZERO_GUID } from '@/TestHelpers';
import { SubjectMap } from '@/editor/domain/SubjectMap';
import { InMemorySubjectLookup } from '@/editor/application/SubjectLookup';
import { PageIdentifiers } from '@/editor/domain/PageIdentifiers';
import { StatementList } from '@/editor/domain/StatementList';

describe( 'Subject', () => {

	it( 'should be constructable via newSubject', () => {
		const subject = newSubject( {
			label: 'I am a tomato',
			schemaId: 'Tomato'
		} );

		expect( subject.getId().text ).toBe( ZERO_GUID );
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

		it( 'should return empty SubjectMap when there are no properties', async () => {
			const subject = newSubject();
			const lookup = new InMemorySubjectLookup( [] );

			expect( await subject.getReferencedSubjects( lookup ) ).toEqual( new SubjectMap() );
		} );

		it( 'should return a SubjectMap with referenced Subjects', async () => {
			const subject1 = newSubject( { id: '00000000-0000-0000-0000-000000000001' } );
			const subject2 = newSubject( { id: '00000000-0000-0000-0000-000000000002' } );
			const subject3 = newSubject( { id: '00000000-0000-0000-0000-000000000003' } );
			const lookup = new InMemorySubjectLookup( [ subject1, subject2, subject3 ] );

			const subject = newSubject( {
				id: ZERO_GUID,
				statements: StatementList.fromPropertyValueRecord( {
					Property1: 'foo',
					Property2: [ { target: '00000000-0000-0000-0000-000000000001' } ],
					Property3: [ { target: '00000000-0000-0000-0000-000000000002' }, { target: '00000000-0000-0000-0000-000000000003' } ],
					Property4: 'bar'
				} )
			} );

			const subjectMap = await subject.getReferencedSubjects( lookup );

			expect( subjectMap ).toEqual( new SubjectMap( subject1, subject2, subject3 ) );
		} );

	} );

} );
