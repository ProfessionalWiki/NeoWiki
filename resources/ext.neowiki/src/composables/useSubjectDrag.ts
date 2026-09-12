import { Ref } from 'vue';
import { useSortable } from '@/composables/useSortable';
import { SubjectId } from '@/domain/SubjectId';
import { subjectIdFromRowDomId } from '@/presentation/subjectRowAnchor';

const DRAG_HANDLE_SELECTOR = '.ext-neowiki-subjects-manager__row-drag-handle';
const GHOST_CLASS = 'ext-neowiki-subjects-manager__row--ghost';
const SORTABLE_GROUP_NAME = 'neowiki-subjects';

export interface SubjectDragHandlers {
	/**
	 * A row from the other-Subjects list was dropped onto the main slot.
	 * `oldOtherIndex` is the source row's index in the other-Subjects list
	 * (used for swap-into-position).
	 */
	onPromote( subjectId: SubjectId, oldOtherIndex: number | undefined ): void;
	/**
	 * The main row was dropped into the other-Subjects list. `newOtherIndex`
	 * is the target slot.
	 */
	onDemote( newOtherIndex: number | undefined ): void;
	/**
	 * A row was reordered within the other-Subjects list.
	 */
	onReorderOthers( oldIndex: number, newIndex: number ): void;
}

export function useSubjectDrag(
	mainSlotRef: Ref<HTMLElement | null>,
	otherListRef: Ref<HTMLElement | null>,
	handlers: SubjectDragHandlers,
): void {
	// sortablejs only consults `put` for inter-list drops, so a same-container
	// drag never enters the cross-container handler. Reorder within the
	// other-Subjects list flows through onReorder; the main slot uses
	// `sort: false` so reordering inside it (a single-item list) is moot.
	const group = { name: SORTABLE_GROUP_NAME, pull: true, put: true };

	useSortable( mainSlotRef, {
		handle: DRAG_HANDLE_SELECTOR,
		ghostClass: GHOST_CLASS,
		group,
		sort: false,
		onDropIn: ( item, oldIndex ) => {
			const id = subjectIdFromRow( item );
			if ( id !== null ) {
				handlers.onPromote( id, oldIndex );
			}
		},
	} );

	useSortable( otherListRef, {
		handle: DRAG_HANDLE_SELECTOR,
		ghostClass: GHOST_CLASS,
		group,
		onDropIn: ( _item, _oldIndex, newIndex ) => {
			handlers.onDemote( newIndex );
		},
		onReorder: ( oldIndex, newIndex ) => {
			handlers.onReorderOthers( oldIndex, newIndex );
		},
	} );
}

function subjectIdFromRow( element: HTMLElement ): SubjectId | null {
	const subjectId = subjectIdFromRowDomId( element.id );
	return subjectId === null ? null : new SubjectId( subjectId );
}
