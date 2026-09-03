import type { Subject } from '@/domain/Subject.ts';

/**
 * The Schema name to show beside a Subject, or null when the Subject's own name is the Schema
 * name already: a Subject with no label is shown under its Schema name (ADR 31), and one
 * labelled after its Schema reads the same. Marked, "(unnamed <Schema>)" still names the
 * Schema, which is why this takes the Subject rather than the name a surface shows for it.
 *
 * The badge does not apply this itself: a surface guards its own wrapper along with the
 * name, since an emptied wrapper carrying a role is worse than a repeat.
 */
export function schemaNameToShow( subject: Subject ): string | null {
	return subject.getDisplayName() === subject.getSchemaName() ? null : subject.getSchemaName();
}
