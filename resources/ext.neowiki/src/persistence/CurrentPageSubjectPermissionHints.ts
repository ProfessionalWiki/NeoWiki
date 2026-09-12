import type { SubjectPermissionHints } from '@/application/SubjectPermissionHints';

/**
 * Answers for the page being viewed with the decision the server made for it, which is the check
 * the server applies to the write as well. Any other page goes to the fallback, since the server
 * was asked about this page only.
 *
 * The one decision answers creating, editing and deleting alike, because every Subject write is an
 * edit of the page holding the Subject. Were the server ever to judge them apart, it would have to
 * say so per question.
 */
export class CurrentPageSubjectPermissionHints implements SubjectPermissionHints {

	public constructor(
		private readonly currentPageId: number,
		private readonly canEditCurrentPage: boolean,
		private readonly otherPages: SubjectPermissionHints,
	) {
	}

	public async canCreateChildSubject( pageId: number ): Promise<boolean> {
		return this.decisionFor( pageId ) ?? this.otherPages.canCreateChildSubject( pageId );
	}

	public async canEditSubject( pageId: number ): Promise<boolean> {
		return this.decisionFor( pageId ) ?? this.otherPages.canEditSubject( pageId );
	}

	public async canDeleteSubject( pageId: number ): Promise<boolean> {
		return this.decisionFor( pageId ) ?? this.otherPages.canDeleteSubject( pageId );
	}

	public async canCreateMainSubject( pageId: number ): Promise<boolean> {
		return this.decisionFor( pageId ) ?? this.otherPages.canCreateMainSubject( pageId );
	}

	/**
	 * The page to hold the new Subject does not exist yet, so there is no page decision to use.
	 */
	public async canCreateSubjectPage(): Promise<boolean> {
		return this.otherPages.canCreateSubjectPage();
	}

	/** Null where the server made no decision, which is every page but the one being viewed. */
	private decisionFor( pageId: number ): boolean | null {
		return pageId === this.currentPageId ? this.canEditCurrentPage : null;
	}

}
