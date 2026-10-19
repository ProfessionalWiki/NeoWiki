import { ref, Ref } from 'vue';

export type SaveHandler = ( summary: string ) => Promise<void>;

interface SaveGuard {
	saving: Ref<boolean>;
	save: SaveHandler;
}

/**
 * Ignores a save while another is in flight, so a second press of Save does not save twice.
 */
export function useSaveGuard( save: SaveHandler ): SaveGuard {
	const saving = ref( false );

	async function guardedSave( summary: string ): Promise<void> {
		if ( saving.value ) {
			return;
		}

		saving.value = true;

		try {
			await save( summary );
		} finally {
			saving.value = false;
		}
	}

	return {
		saving,
		save: guardedSave,
	};
}
