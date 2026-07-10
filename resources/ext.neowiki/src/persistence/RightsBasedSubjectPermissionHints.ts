import type { SubjectPermissionHints } from '@/application/SubjectPermissionHints';
import type { SubjectId } from '@/domain/SubjectId';
import type { RightsFetcher } from '@/persistence/UserObjectBasedRightsFetcher';

/**
 * Creating, editing and deleting a Subject are all edits of the page that holds it, so they all
 * hint on the 'edit' right. This matches the authorization the server applies to the write.
 *
 * TODO: the server checks 'edit' on the specific page, while these hints check the wiki-global
 * right. A user who may edit globally but not the page at hand is offered affordances that the
 * server then rejects.
 */
export class RightsBasedSubjectPermissionHints implements SubjectPermissionHints {

	public constructor( private readonly rightsFetcher: RightsFetcher ) {
	}

	public async canCreateChildSubject( _pageId: number ): Promise<boolean> {
		return this.canEditPage();
	}

	public async canEditSubject( _subjectId: SubjectId ): Promise<boolean> {
		return this.canEditPage();
	}

	public async canDeleteSubject( _subjectId: SubjectId ): Promise<boolean> {
		return this.canEditPage();
	}

	public async canCreateMainSubject(): Promise<boolean> {
		return this.canEditPage();
	}

	private async canEditPage(): Promise<boolean> {
		const rights = await this.rightsFetcher.getRights();
		return rights.includes( 'edit' );
	}
}
