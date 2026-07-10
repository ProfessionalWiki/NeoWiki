import type { SubjectId } from '@/domain/SubjectId';

/**
 * Hints for showing or hiding affordances. They are advisory, not a security control: the server
 * authorizes every Subject write, and it is the only thing that can. A positive answer here means
 * "offer the affordance", never "the write will succeed".
 */
export interface SubjectPermissionHints {

	canCreateChildSubject( pageId: number ): Promise<boolean>;

	canEditSubject( subjectId: SubjectId ): Promise<boolean>;

	canDeleteSubject( subjectId: SubjectId ): Promise<boolean>;

	canCreateMainSubject(): Promise<boolean>;
}
