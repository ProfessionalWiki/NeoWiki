import Sortable from 'sortablejs';
import { onBeforeUnmount, Ref, watch } from 'vue';

export interface UseSortableOptions {
	handle?: string;
	draggable?: string;
	ghostClass?: string;
	group?: Sortable.Options[ 'group' ];
	sort?: boolean;
	onReorder?: ( oldIndex: number, newIndex: number ) => void;
	onDropIn?: ( item: HTMLElement, oldIndex: number | undefined, newIndex: number | undefined ) => void;
}

export function useSortable( containerRef: Ref<HTMLElement | null>, options: UseSortableOptions ): void {
	let instance: Sortable | null = null;

	function attach( container: HTMLElement ): void {
		// Build options conditionally: sortablejs merges with its defaults using
		// Object.assign, so passing `undefined` for `sort`, `group`, or
		// `draggable` clobbers sortablejs's default (e.g. sort: true,
		// draggable: '>*') and silently disables reordering or makes nothing
		// draggable.
		const sortableOptions: Sortable.Options = {
			handle: options.handle,
			animation: 150,
			ghostClass: options.ghostClass ?? 'ext-neowiki-property-list__item--ghost',
			onEnd: ( event ) => {
				// Revert sortablejs's DOM mutation FIRST so Vue can re-render from
				// store state without fighting it. Handles within-container
				// (currentParent === from) and cross-container (currentParent === to)
				// cases alike. Only after the revert do we emit, so the consumer's
				// reactive update lands on a clean DOM.
				const { item, from, to, oldIndex, newIndex } = event;
				const currentParent = item.parentNode;
				if ( currentParent !== null ) {
					currentParent.removeChild( item );
				}
				from.insertBefore( item, from.children[ oldIndex! ] || null );

				if ( from === to && oldIndex !== undefined && newIndex !== undefined && oldIndex !== newIndex ) {
					options.onReorder?.( oldIndex, newIndex );
				}
			},
			onAdd: ( event ) => {
				options.onDropIn?.( event.item, event.oldIndex, event.newIndex );
			},
		};
		if ( options.group !== undefined ) {
			sortableOptions.group = options.group;
		}
		if ( options.sort !== undefined ) {
			sortableOptions.sort = options.sort;
		}
		if ( options.draggable !== undefined ) {
			sortableOptions.draggable = options.draggable;
		}
		instance = Sortable.create( container, sortableOptions );
	}

	function detach(): void {
		instance?.destroy();
		instance = null;
	}

	// Reactive attach/detach: the container may not be in the DOM at mount time
	// (e.g. when its parent is rendered conditionally after an async load), and
	// can disappear or be replaced on re-render. `immediate` covers the case
	// where the container is already attached when this composable runs.
	watch(
		containerRef,
		( container ) => {
			detach();
			if ( container !== null ) {
				attach( container );
			}
		},
		{ immediate: true, flush: 'post' },
	);

	onBeforeUnmount( detach );
}
