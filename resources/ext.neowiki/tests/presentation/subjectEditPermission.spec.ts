import { describe, expect, it } from 'vitest';
import { canEditSubjectOnItsPage } from '@/presentation/subjectEditPermission';
import { Subject } from '@/domain/Subject';
import { SubjectWithContext } from '@/domain/SubjectWithContext';
import { SubjectId } from '@/domain/SubjectId';
import { PageIdentifiers } from '@/domain/PageIdentifiers';
import { StatementList } from '@/domain/StatementList';
import type { SubjectPermissionHints } from '@/application/SubjectPermissionHints';

describe( 'canEditSubjectOnItsPage', () => {

	const HOSTING_PAGE = 42;

	function hintsAllowingOnly( editablePageId: number ): SubjectPermissionHints & { askedAbout: number[] } {
		const askedAbout: number[] = [];

		return {
			askedAbout,
			canCreateChildSubject: async () => false,
			canEditSubject: async ( pageId: number ) => {
				askedAbout.push( pageId );
				return pageId === editablePageId;
			},
			canDeleteSubject: async () => false,
			canCreateMainSubject: async () => false,
			canCreateSubjectPage: async () => false,
		};
	}

	function subjectOnPage( pageId: number ): SubjectWithContext {
		return new SubjectWithContext(
			new SubjectId( 's11111111111117' ),
			'Acme Rocket',
			'Acme Rocket',
			false,
			'Product',
			new StatementList( [] ),
			new PageIdentifiers( pageId, 'Acme Rocket' ),
		);
	}

	function subjectWithoutPage(): Subject {
		return new Subject(
			new SubjectId( 's11111111111117' ),
			'Acme Rocket',
			'Acme Rocket',
			false,
			'Product',
			new StatementList( [] ),
		);
	}

	it( 'asks about the page holding the Subject', async () => {
		const hints = hintsAllowingOnly( HOSTING_PAGE );

		expect( await canEditSubjectOnItsPage( subjectOnPage( HOSTING_PAGE ), hints ) ).toBe( true );
		expect( hints.askedAbout ).toEqual( [ HOSTING_PAGE ] );
	} );

	it( 'reports a Subject on a page the viewer cannot edit as not editable', async () => {
		const hints = hintsAllowingOnly( HOSTING_PAGE );

		expect( await canEditSubjectOnItsPage( subjectOnPage( HOSTING_PAGE + 1 ), hints ) ).toBe( false );
	} );

	it( 'offers no editing for a Subject that carries no page, such as one from another Source', async () => {
		const hints = hintsAllowingOnly( HOSTING_PAGE );

		expect( await canEditSubjectOnItsPage( subjectWithoutPage(), hints ) ).toBe( false );
		expect( hints.askedAbout ).toEqual( [] );
	} );

	it( 'offers no editing for a Subject that did not load', async () => {
		const hints = hintsAllowingOnly( HOSTING_PAGE );

		expect( await canEditSubjectOnItsPage( undefined, hints ) ).toBe( false );
	} );

} );
