import { describe, expect, it } from 'vitest';
import { RightsBasedSubjectAuthorizer } from '@/persistence/RightsBasedSubjectAuthorizer';
import { SubjectId } from '@/domain/SubjectId';
import { TestUserObjectBasedRightsFetcher } from './UserObjectBasedRightsFetcher.unit.spec';

describe( 'Rights Based Subject Authorizer', async () => {

	const SUBJECT_ID = new SubjectId( 's11111111111117' );

	function newAuthorizer( rights: string[] ): RightsBasedSubjectAuthorizer {
		return new RightsBasedSubjectAuthorizer( new TestUserObjectBasedRightsFetcher( rights ) );
	}

	function withEditRight(): RightsBasedSubjectAuthorizer {
		return newAuthorizer( [ 'foo', 'edit', 'bar', 'baz' ] );
	}

	function withoutEditRight(): RightsBasedSubjectAuthorizer {
		return newAuthorizer( [ 'foo', 'bar', 'baz' ] );
	}

	it( 'can edit subject with edit right', async () => {
		expect( await withEditRight().canEditSubject( SUBJECT_ID ) ).toBe( true );
	} );

	it( 'cannot edit subject without edit right', async () => {
		expect( await withoutEditRight().canEditSubject( SUBJECT_ID ) ).toBe( false );
	} );

	it( 'can delete subject with edit right', async () => {
		expect( await withEditRight().canDeleteSubject( SUBJECT_ID ) ).toBe( true );
	} );

	it( 'cannot delete subject without edit right', async () => {
		expect( await withoutEditRight().canDeleteSubject( SUBJECT_ID ) ).toBe( false );
	} );

	it( 'does not need the delete right to delete a subject', async () => {
		const authorizer = newAuthorizer( [ 'foo', 'edit', 'bar' ] );

		expect( await authorizer.canDeleteSubject( SUBJECT_ID ) ).toBe( true );
	} );

	it( 'cannot delete subject with only the delete right', async () => {
		const authorizer = newAuthorizer( [ 'foo', 'delete', 'bar' ] );

		expect( await authorizer.canDeleteSubject( SUBJECT_ID ) ).toBe( false );
	} );

	it( 'can create child subject with edit right', async () => {
		expect( await withEditRight().canCreateChildSubject( 42 ) ).toBe( true );
	} );

	it( 'cannot create child subject without edit right', async () => {
		expect( await withoutEditRight().canCreateChildSubject( 42 ) ).toBe( false );
	} );

	it( 'does not need the createpage right to create a child subject', async () => {
		const authorizer = newAuthorizer( [ 'foo', 'edit', 'bar' ] );

		expect( await authorizer.canCreateChildSubject( 42 ) ).toBe( true );
	} );

	it( 'can create main subject with edit right', async () => {
		expect( await withEditRight().canCreateMainSubject() ).toBe( true );
	} );

	it( 'cannot create main subject without edit right', async () => {
		expect( await withoutEditRight().canCreateMainSubject() ).toBe( false );
	} );

	it( 'does not need the createpage right to create a main subject', async () => {
		const authorizer = newAuthorizer( [ 'foo', 'edit', 'bar' ] );

		expect( await authorizer.canCreateMainSubject() ).toBe( true );
	} );

} );
