/**
 * Hints for showing or hiding affordances. They are advisory, not a security control: the server
 * authorizes every Subject write, and it is the only thing that can. A positive answer here means
 * "offer the affordance", never "the write will succeed".
 *
 * Creating, editing and deleting a Subject are all edits of the page holding it, so each of those
 * hints is asked about that page.
 */
export interface SubjectPermissionHints {

	canCreateChildSubject( pageId: number ): Promise<boolean>;

	canEditSubject( pageId: number ): Promise<boolean>;

	canDeleteSubject( pageId: number ): Promise<boolean>;

	canCreateMainSubject( pageId: number ): Promise<boolean>;

	canCreateSubjectPage(): Promise<boolean>;
}
