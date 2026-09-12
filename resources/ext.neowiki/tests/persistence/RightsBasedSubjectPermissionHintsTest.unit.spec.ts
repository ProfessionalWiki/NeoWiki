import { describe, expect, it } from 'vitest';
import { RightsBasedSubjectPermissionHints } from '@/persistence/RightsBasedSubjectPermissionHints';
import { SubjectId } from '@/domain/SubjectId';
import { TestUserObjectBasedRightsFetcher } from './UserObjectBasedRightsFetcher.unit.spec';

describe( 'Rights Based Subject Permission Hints', async () => {

	const SUBJECT_ID = new SubjectId( 's11111111111117' );

	function newHints( rights: string[] ): RightsBasedSubjectPermissionHints {
		return new RightsBasedSubjectPermissionHints( new TestUserObjectBasedRightsFetcher( rights ) );
	}

	function withEditRight(): RightsBasedSubjectPermissionHints {
		return newHints( [ 'foo', 'edit', 'bar', 'baz' ] );
	}

	function withoutEditRight(): RightsBasedSubjectPermissionHints {
		return newHints( [ 'foo', 'bar', 'baz' ] );
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
		const hints = newHints( [ 'foo', 'edit', 'bar' ] );

		expect( await hints.canDeleteSubject( SUBJECT_ID ) ).toBe( true );
	} );

	it( 'cannot delete subject with only the delete right', async () => {
		const hints = newHints( [ 'foo', 'delete', 'bar' ] );

		expect( await hints.canDeleteSubject( SUBJECT_ID ) ).toBe( false );
	} );

	it( 'can create other subject with edit right', async () => {
		expect( await withEditRight().canCreateOtherSubject( 42 ) ).toBe( true );
	} );

	it( 'cannot create other subject without edit right', async () => {
		expect( await withoutEditRight().canCreateOtherSubject( 42 ) ).toBe( false );
	} );

	it( 'does not need the createpage right to create one of the other subjects', async () => {
		const hints = newHints( [ 'foo', 'edit', 'bar' ] );

		expect( await hints.canCreateOtherSubject( 42 ) ).toBe( true );
	} );

	it( 'can create main subject with edit right', async () => {
		expect( await withEditRight().canCreateMainSubject() ).toBe( true );
	} );

	it( 'cannot create main subject without edit right', async () => {
		expect( await withoutEditRight().canCreateMainSubject() ).toBe( false );
	} );

	it( 'does not need the createpage right to create a main subject', async () => {
		const hints = newHints( [ 'foo', 'edit', 'bar' ] );

		expect( await hints.canCreateMainSubject() ).toBe( true );
	} );

	it( 'can create a subject page with the createpage and edit rights', async () => {
		expect( await newHints( [ 'createpage', 'edit' ] ).canCreateSubjectPage() ).toBe( true );
	} );

	it( 'cannot create a subject page without the createpage right', async () => {
		expect( await newHints( [ 'edit' ] ).canCreateSubjectPage() ).toBe( false );
	} );

	it( 'cannot create a subject page without the edit right', async () => {
		expect( await newHints( [ 'createpage' ] ).canCreateSubjectPage() ).toBe( false );
	} );

} );
