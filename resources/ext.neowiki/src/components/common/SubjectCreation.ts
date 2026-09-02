import type { InjectionKey } from 'vue';
import type { Subject } from '@/domain/Subject';

/**
 * How a host lets a picker invent a Subject the wiki does not hold yet, and reach the ones it has
 * already invented. Provided by an editor that can carry such Subjects until they are saved; a
 * picker without it offers only Subjects the wiki already has.
 */
export interface SubjectCreation {

	/**
	 * Creates a Subject of the given Schema, labelled with the text the user typed, or with nothing
	 * when they typed none. Resolves to null when the host refused or the creation failed; the host
	 * reports that failure itself.
	 */
	create( schemaName: string, label: string | null ): Promise<Subject | null>;

	/**
	 * The Subjects of the given Schema this editing session has invented, as they currently stand:
	 * no search can return them, because the server has never been told they exist, and their names
	 * follow the editor rather than any stored value. Read inside a computed to track renames.
	 */
	drafts( schemaName: string ): readonly Subject[];

}

export const SubjectCreationKey: InjectionKey<SubjectCreation> = Symbol( 'NeoWikiSubjectCreation' );
