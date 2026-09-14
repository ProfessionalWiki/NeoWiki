import type { SubjectPermissionHints } from '@/application/SubjectPermissionHints';
import type { RightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher';

/**
 * Creating, editing and deleting a Subject are all edits of the page that holds it, so they all
 * hint on the 'edit' right. This matches the authorization the server applies to the write.
 *
 * The wiki-global right is all these have, so they answer the same for every page, while the
 * server decides per page. CurrentPageSubjectPermissionHints closes that gap for the page being
 * viewed; elsewhere a user who may edit globally but not the page at hand is still offered
 * affordances the server then rejects.
 */
export class RightsBasedSubjectPermissionHints implements SubjectPermissionHints {

	public constructor( private readonly rightsFetcher: RightsFetcher ) {
	}

	public async canCreateChildSubject( _pageId: number ): Promise<boolean> {
		return this.canEditPage();
	}

	public async canEditSubject( _pageId: number ): Promise<boolean> {
		return this.canEditPage();
	}

	public async canDeleteSubject( _pageId: number ): Promise<boolean> {
		return this.canEditPage();
	}

	public async canCreateMainSubject( _pageId: number ): Promise<boolean> {
		return this.canEditPage();
	}

	public async canCreateSubjectPage(): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'createpage' ) && rights.includes( 'edit' );
	}

	private async canEditPage(): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'edit' );
	}
}
