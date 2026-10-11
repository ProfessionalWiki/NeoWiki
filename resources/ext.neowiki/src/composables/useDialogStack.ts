import { computed, onScopeDispose, ref, toValue, watch } from 'vue';
import type { ComputedRef, MaybeRefOrGetter } from 'vue';

// The dialog that joined last is in front: Codex orders dialogs by when they mount, and each one that
// opens over another mounts after it. Only the front is reactive: dialogs leave during their parent's
// render, which would otherwise come to depend on every later join and leave.
const openDialogs: symbol[] = [];
const frontDialog = ref<symbol | undefined>();

/**
 * Keeps the calling component's dialog in the stack while `open` holds, and tells it whether that
 * dialog is in front. Every dialog that can open in front of one holding the save key must join, or
 * the key stays live behind it.
 */
export function useDialogStack( open: MaybeRefOrGetter<boolean> ): ComputedRef<boolean> {
	const dialog = Symbol( 'dialog' );

	function leave(): void {
		const index = openDialogs.indexOf( dialog );

		if ( index !== -1 ) {
			openDialogs.splice( index, 1 );
			frontDialog.value = openDialogs[ openDialogs.length - 1 ];
		}
	}

	watch( () => toValue( open ), ( isOpen ) => {
		if ( isOpen ) {
			openDialogs.push( dialog );
			frontDialog.value = dialog;
		} else {
			leave();
		}
	}, { immediate: true } );

	onScopeDispose( leave );

	return computed( () => frontDialog.value === dialog );
}
