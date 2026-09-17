import { ref, shallowRef, type Ref, type ShallowRef } from 'vue';
import type { Schema } from '@/domain/Schema.ts';
import type { SchemaLookup } from '@/application/SchemaLookup.ts';
import type { Subject } from '@/domain/Subject.ts';
import type { SubjectId } from '@/domain/SubjectId.ts';
import type { SubjectRepository } from '@/domain/SubjectRepository.ts';

export interface UseSubjectEditor {
	editingSubject: ShallowRef<Subject | null>;
	editingSchema: ShallowRef<Schema | null>;
	editorOpen: Ref<boolean>;
	openEditor: ( id: SubjectId ) => Promise<void>;
}

/**
 * An editor opens on freshly read data, which is the opener's job rather than the dialog's
 * (ADR 30): the stores hold the page payload and this session's own writes, so they can be behind
 * another tab or another user. The dialog edits its own copy, so the display a host renders from
 * the stores stays put until a save answers with the Subject as persisted.
 *
 * Reading the Schema off the fetched Subject means no caller has to hold a Subject to open an
 * editor, and none can pair a Subject with a Schema it does not instantiate.
 */
export function useSubjectEditor(
	subjectRepository: Pick<SubjectRepository, 'getSubjectForEditing'>,
	schemaLookup: Pick<SchemaLookup, 'getSchema'>,
): UseSubjectEditor {
	const editingSubject = shallowRef<Subject | null>( null );
	const editingSchema = shallowRef<Schema | null>( null );
	const editorOpen = ref( false );

	// A row list opens the editor from many buttons, none of them disabled while a read is in
	// flight. Only the newest open may land, or a slower earlier one replaces the Subject under a
	// dialog the user is already typing into.
	let latestOpen = 0;

	async function openEditor( id: SubjectId ): Promise<void> {
		const request = ++latestOpen;

		try {
			const subject = await subjectRepository.getSubjectForEditing( id );

			if ( request !== latestOpen ) {
				return;
			}

			const schema = await schemaLookup.getSchema( subject.getSchemaName() );

			if ( request !== latestOpen ) {
				return;
			}

			// Assigned only once both have landed, so a failed Schema read cannot leave the
			// editor open on a Subject with no Schema to render it by.
			editingSubject.value = subject;
			editingSchema.value = schema;
			editorOpen.value = true;
		} catch ( error ) {
			if ( request !== latestOpen ) {
				return;
			}

			mw.notify(
				error instanceof Error ? error.message : String( error ),
				{ type: 'error' },
			);
		}
	}

	return { editingSubject, editingSchema, editorOpen, openEditor };
}
