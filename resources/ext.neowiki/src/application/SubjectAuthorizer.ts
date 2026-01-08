import type { SubjectId } from '@/domain/SubjectId';

export interface SubjectAuthorizer {

	canCreateChildSubject( pageId: number ): Promise<boolean>;

	canEditSubject( subjectId: SubjectId ): Promise<boolean>;

	canDeleteSubject( subjectId: SubjectId ): Promise<boolean>;

	canCreateMainSubject(): Promise<boolean>;
}
