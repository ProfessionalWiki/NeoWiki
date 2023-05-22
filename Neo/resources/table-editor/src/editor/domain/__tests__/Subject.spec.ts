import { describe, expect, it } from 'vitest';
import { newTestSubject, ZERO_GUID } from '../TestSubject';
import { PageIdentifiers } from '../Subject';
import { SubjectMap } from '../SubjectMap';
import { InMemorySubjectLookup } from '../../application/SubjectLookup';
import { SubjectId } from '../SubjectId';

describe( 'Subject', () => {

	it( 'should be constructable via newTestSubject', () => {
		const subject = newTestSubject( {
			label: 'I am a tomato',
			schemaId: 'Tomato'
		} );

		expect( subject.getId().text ).toBe( ZERO_GUID );
		expect( subject.getLabel() ).toBe( 'I am a tomato' );
		expect( subject.getSchemaId() ).toBe( 'Tomato' );
		expect( subject.getPageIdentifiers().getPageName() ).toBe( 'TestSubjectPage' );
	} );

	it( 'should store page identifiers', () => {
		const identifiers = new PageIdentifiers( 123, 'TestPage' );

		const subject = newTestSubject( {
			pageIdentifiers: identifiers
		} );

		expect( subject.getPageIdentifiers() ).toEqual( identifiers );
	} );

	describe( 'getIdsOfReferencedSubjects', () => {

		it( 'should return empty list when there are no properties', () => {
			const subject = newTestSubject();

			expect( subject.getIdsOfReferencedSubjects() ).toEqual( [] );
		} );

		it( 'should return a list of referenced SubjectIds when relations exist', () => {
			const subject = newTestSubject( {
				properties: {
					Property1: 'foo',
					Property2: [ { target: '00000000-0000-0000-0000-000000000001' } ],
					Property3: [ { target: '00000000-0000-0000-0000-000000000002' }, { target: '00000000-0000-0000-0000-000000000003' } ],
					Property4: 'bar'
				}
			} );

			expect( subject.getIdsOfReferencedSubjects() ).toEqual( [
				new SubjectId( '00000000-0000-0000-0000-000000000001' ),
				new SubjectId( '00000000-0000-0000-0000-000000000002' ),
				new SubjectId( '00000000-0000-0000-0000-000000000003' )
			] );
		} );

	} );

	describe( 'getReferencedSubjects', () => {

		it( 'should return empty SubjectMap when there are no properties', async () => {
			const subject = newTestSubject();
			const lookup = new InMemorySubjectLookup( [] );

			expect( await subject.getReferencedSubjects( lookup ) ).toEqual( new SubjectMap() );
		} );

		it( 'should return a SubjectMap with referenced Subjects', async () => {
			const subject1 = newTestSubject( { id: '00000000-0000-0000-0000-000000000001' } );
			const subject2 = newTestSubject( { id: '00000000-0000-0000-0000-000000000002' } );
			const subject3 = newTestSubject( { id: '00000000-0000-0000-0000-000000000003' } );
			const lookup = new InMemorySubjectLookup( [ subject1, subject2, subject3 ] );

			const subject = newTestSubject( {
				id: ZERO_GUID,
				properties: {
					Property1: 'foo',
					Property2: [ { target: '00000000-0000-0000-0000-000000000001' } ],
					Property3: [ { target: '00000000-0000-0000-0000-000000000002' }, { target: '00000000-0000-0000-0000-000000000003' } ],
					Property4: 'bar'
				}
			} );

			const subjectMap = await subject.getReferencedSubjects( lookup );

			expect( subjectMap ).toEqual( new SubjectMap( subject1, subject2, subject3 ) );
		} );

	} );

} );
