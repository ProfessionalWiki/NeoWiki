import type { Subject } from '@/domain/Subject';
import { SubjectWithContext } from '@/domain/SubjectWithContext';
import type { SubjectPermissionHints } from '@/application/SubjectPermissionHints';

/**
 * Whether to offer editing a Subject, asked of the page that holds it — the page the server
 * authorizes the write against, which is not always the page the Subject is shown on.
 *
 * A Subject with no page holds nothing to ask about: one from another Source is read-only, and one
 * that did not load cannot be edited either.
 */
export async function canEditSubjectOnItsPage(
	subject: Subject | undefined,
	hints: SubjectPermissionHints,
): Promise<boolean> {
	const pageId = subject instanceof SubjectWithContext ? subject.getPageIdentifiers().getPageId() : undefined;

	if ( !Number.isInteger( pageId ) ) {
		return false;
	}

	return hints.canEditSubject( pageId as number );
}
