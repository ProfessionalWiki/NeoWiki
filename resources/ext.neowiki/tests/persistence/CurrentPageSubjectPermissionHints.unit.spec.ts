import { describe, expect, it } from 'vitest';
import { CurrentPageSubjectPermissionHints } from '@/persistence/CurrentPageSubjectPermissionHints';
import type { SubjectPermissionHints } from '@/application/SubjectPermissionHints';

describe( 'Current Page Subject Permission Hints', () => {

	const CURRENT_PAGE = 42;
	const OTHER_PAGE = 43;

	type Ask = ( hints: SubjectPermissionHints, pageId: number ) => Promise<boolean>;

	const pageKeyedHints: [ string, Ask ][] = [
		[ 'create a main subject', ( hints, pageId ) => hints.canCreateMainSubject( pageId ) ],
		[ 'create a child subject', ( hints, pageId ) => hints.canCreateChildSubject( pageId ) ],
		[ 'edit a subject', ( hints, pageId ) => hints.canEditSubject( pageId ) ],
		[ 'delete a subject', ( hints, pageId ) => hints.canDeleteSubject( pageId ) ],
	];

	function hintsAnswering( answer: boolean ): SubjectPermissionHints {
		return {
			canCreateMainSubject: async () => answer,
			canCreateChildSubject: async () => answer,
			canEditSubject: async () => answer,
			canDeleteSubject: async () => answer,
			canCreateSubjectPage: async () => answer,
		};
	}

	function newHints( canEditCurrentPage: boolean ): CurrentPageSubjectPermissionHints {
		return new CurrentPageSubjectPermissionHints(
			CURRENT_PAGE,
			canEditCurrentPage,
			hintsAnswering( !canEditCurrentPage ),
		);
	}

	it.each( pageKeyedHints )( 'reports that the viewer may %s on the current page', async ( _name, ask ) => {
		expect( await ask( newHints( true ), CURRENT_PAGE ) ).toBe( true );
	} );

	it.each( pageKeyedHints )( 'reports that the viewer may not %s on a current page they cannot edit', async ( _name, ask ) => {
		expect( await ask( newHints( false ), CURRENT_PAGE ) ).toBe( false );
	} );

	it.each( pageKeyedHints )( 'asks the fallback whether the viewer may %s on another page', async ( _name, ask ) => {
		const hints = new CurrentPageSubjectPermissionHints( CURRENT_PAGE, false, hintsAnswering( true ) );

		expect( await ask( hints, OTHER_PAGE ) ).toBe( true );
	} );

	it( 'asks the fallback whether a Subject page can be created, which is about no page yet', async () => {
		const hints = new CurrentPageSubjectPermissionHints( CURRENT_PAGE, false, hintsAnswering( true ) );

		expect( await hints.canCreateSubjectPage() ).toBe( true );
	} );

} );
