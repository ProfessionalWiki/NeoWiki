import { describe, expect, it } from 'vitest';
import { RightsBasedSubjectPermissionHints } from '@/persistence/RightsBasedSubjectPermissionHints';
import { TestUserObjectBasedRightsFetcher } from './UserObjectBasedRightsFetcher.unit.spec';

describe( 'Rights Based Subject Permission Hints', async () => {

	const PAGE_ID = 42;

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
		expect( await withEditRight().canEditSubject( PAGE_ID ) ).toBe( true );
	} );

	it( 'cannot edit subject without edit right', async () => {
		expect( await withoutEditRight().canEditSubject( PAGE_ID ) ).toBe( false );
	} );

	it( 'can delete subject with edit right', async () => {
		expect( await withEditRight().canDeleteSubject( PAGE_ID ) ).toBe( true );
	} );

	it( 'cannot delete subject without edit right', async () => {
		expect( await withoutEditRight().canDeleteSubject( PAGE_ID ) ).toBe( false );
	} );

	it( 'does not need the delete right to delete a subject', async () => {
		const hints = newHints( [ 'foo', 'edit', 'bar' ] );

		expect( await hints.canDeleteSubject( PAGE_ID ) ).toBe( true );
	} );

	it( 'cannot delete subject with only the delete right', async () => {
		const hints = newHints( [ 'foo', 'delete', 'bar' ] );

		expect( await hints.canDeleteSubject( PAGE_ID ) ).toBe( false );
	} );

	it( 'can create child subject with edit right', async () => {
		expect( await withEditRight().canCreateChildSubject( PAGE_ID ) ).toBe( true );
	} );

	it( 'cannot create child subject without edit right', async () => {
		expect( await withoutEditRight().canCreateChildSubject( PAGE_ID ) ).toBe( false );
	} );

	it( 'does not need the createpage right to create a child subject', async () => {
		const hints = newHints( [ 'foo', 'edit', 'bar' ] );

		expect( await hints.canCreateChildSubject( PAGE_ID ) ).toBe( true );
	} );

	it( 'can create main subject with edit right', async () => {
		expect( await withEditRight().canCreateMainSubject( PAGE_ID ) ).toBe( true );
	} );

	it( 'cannot create main subject without edit right', async () => {
		expect( await withoutEditRight().canCreateMainSubject( PAGE_ID ) ).toBe( false );
	} );

	it( 'does not need the createpage right to create a main subject', async () => {
		const hints = newHints( [ 'foo', 'edit', 'bar' ] );

		expect( await hints.canCreateMainSubject( PAGE_ID ) ).toBe( true );
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
