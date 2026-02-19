import Sortable from 'sortablejs';
import { onBeforeUnmount, onMounted, Ref } from 'vue';

export interface UseSortableOptions {
	handle?: string;
	onReorder: ( oldIndex: number, newIndex: number ) => void;
}

export function useSortable( containerRef: Ref<HTMLElement | null>, options: UseSortableOptions ): void {
	let instance: Sortable | null = null;

	onMounted( () => {
		if ( !containerRef.value ) {
			return;
		}

		instance = Sortable.create( containerRef.value, {
			handle: options.handle,
			animation: 150,
			ghostClass: 'ext-neowiki-property-list__item--ghost',
			onEnd: ( event ) => {
				const { item, from, oldIndex, newIndex } = event;

				// Revert DOM so Vue can re-render correctly
				from.removeChild( item );
				from.insertBefore( item, from.children[ oldIndex! ] || null );

				if ( oldIndex !== undefined && newIndex !== undefined && oldIndex !== newIndex ) {
					options.onReorder( oldIndex, newIndex );
				}
			},
		} );
	} );

	onBeforeUnmount( () => {
		instance?.destroy();
		instance = null;
	} );
}
