import type { Subject } from '@/domain/Subject.ts';
import { schemaDisplayNameOf } from '@/presentation/schemaDisplayName.ts';

/**
 * The name of the Schema to badge beside a Subject, or null when the Subject is shown under what the
 * badge would read already, as one labelled after its Schema is. A Subject nobody named is shown under
 * its id, which says nothing about what it is, so it always gets the badge.
 *
 * The badge does not apply this itself: a surface guards its own wrapper along with the
 * name, since an emptied wrapper carrying a role is worse than a repeat.
 *
 * For surfaces where the Schema is read rather than followed. The Subject editor's pane shows its
 * badge unconditionally, because there the badge is a link and a way into the Schema editor, which
 * a repeated word is not.
 */
export function schemaNameToShow( subject: Subject ): string | null {
	if ( subject.hasGeneratedDisplayName() ) {
		return subject.getSchemaName();
	}

	return subject.getDisplayName() === subjectSchemaDisplayName( subject ) ? null : subject.getSchemaName();
}

/**
 * What the badge beside the Subject reads: schemaDisplayName() for its Schema.
 */
export function subjectSchemaDisplayName( subject: Subject ): string {
	return schemaDisplayNameOf( subject.getSchemaName(), subject.getSchemaLabels() );
}
