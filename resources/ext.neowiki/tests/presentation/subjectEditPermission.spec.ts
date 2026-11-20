import { describe, expect, it } from 'vitest';
import { canEditSubjectOnItsPage } from '@/presentation/subjectEditPermission';
import { Subject } from '@/domain/Subject';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { newSchema, newSubject, unresolvedPage } from '@/TestHelpers';
import type { SubjectPermissionHints } from '@/application/SubjectPermissionHints';

describe( 'canEditSubjectOnItsPage', () => {

	const HOSTING_PAGE = 42;

	const UNCONTROLLED = newSchema( { title: 'Person' } );
	const CONTROLLED = newSchema( { title: 'Gender', subjectsAreControlled: true } );

	function hintsAllowingOnly(
		editablePageId: number,
		canEditControlledSubjects = false,
	): SubjectPermissionHints & { askedAbout: number[] } {
		const askedAbout: number[] = [];

		return {
			askedAbout,
			canCreateOtherSubject: async () => false,
			canEditSubject: async ( pageId: number ) => {
				askedAbout.push( pageId );
				return pageId === editablePageId;
			},
			canDeleteSubject: async () => false,
			canCreateMainSubject: async () => false,
			canCreateSubjectPage: async () => false,
			canEditControlledSubjects: async () => canEditControlledSubjects,
		};
	}

	function subjectOnPage( pageId: number ): Subject {
		return newSubject( { pageIdentifiers: new PageIdentifiers( pageId, 'Acme Rocket' ) } );
	}

	function subjectWithoutPage(): Subject {
		return newSubject( { pageIdentifiers: unresolvedPage() } );
	}

	it( 'asks about the page holding the Subject', async () => {
		const hints = hintsAllowingOnly( HOSTING_PAGE );

		expect( await canEditSubjectOnItsPage( subjectOnPage( HOSTING_PAGE ), UNCONTROLLED, hints ) ).toBe( true );
		expect( hints.askedAbout ).toEqual( [ HOSTING_PAGE ] );
	} );

	it( 'reports a Subject on a page the viewer cannot edit as not editable', async () => {
		const hints = hintsAllowingOnly( HOSTING_PAGE );

		expect( await canEditSubjectOnItsPage( subjectOnPage( HOSTING_PAGE + 1 ), UNCONTROLLED, hints ) ).toBe( false );
	} );

	it( 'offers no editing for a Subject that carries no page, such as one from another Source', async () => {
		const hints = hintsAllowingOnly( HOSTING_PAGE );

		expect( await canEditSubjectOnItsPage( subjectWithoutPage(), UNCONTROLLED, hints ) ).toBe( false );
		expect( hints.askedAbout ).toEqual( [] );
	} );

	it( 'offers no editing for a Subject that did not load', async () => {
		const hints = hintsAllowingOnly( HOSTING_PAGE );

		expect( await canEditSubjectOnItsPage( undefined, undefined, hints ) ).toBe( false );
	} );

	it( 'offers no editing for a Controlled Subject without the right to edit Controlled Subjects', async () => {
		const hints = hintsAllowingOnly( HOSTING_PAGE );

		expect( await canEditSubjectOnItsPage( subjectOnPage( HOSTING_PAGE ), CONTROLLED, hints ) ).toBe( false );
	} );

	it( 'offers editing a Controlled Subject with the right to edit Controlled Subjects', async () => {
		const hints = hintsAllowingOnly( HOSTING_PAGE, true );

		expect( await canEditSubjectOnItsPage( subjectOnPage( HOSTING_PAGE ), CONTROLLED, hints ) ).toBe( true );
	} );

} );
