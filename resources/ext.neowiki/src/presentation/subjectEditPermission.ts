import type { Subject } from '@/domain/Subject';
import type { Schema } from '@/domain/Schema';
import type { SubjectPermissionHints } from '@/application/SubjectPermissionHints';

/**
 * Whether to offer editing a Subject, asked of the page that holds it — the page the server
 * authorizes the write against, which is not always the page the Subject is shown on. A Controlled
 * Subject needs the right to edit Controlled Subjects as well.
 *
 * A Subject with no page holds nothing to ask about: one from another Source is read-only, and one
 * that did not load cannot be edited either.
 */
export async function canEditSubjectOnItsPage(
	subject: Subject | undefined,
	schema: Pick<Schema, 'subjectsAreControlled'> | undefined,
	hints: SubjectPermissionHints,
): Promise<boolean> {
	const pageId = subject?.getPageIdentifiers().getPageId();

	if ( !Number.isInteger( pageId ) ) {
		return false;
	}

	return await hints.canEditSubject( pageId as number ) &&
		( schema?.subjectsAreControlled() !== true || await hints.canEditControlledSubjects() );
}
