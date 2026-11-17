import { computed, type ComputedRef, ref, watch } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

/**
 * Whether Subjects may use the Schema, asked of the wiki each time `asking` turns true and whenever the Schema changes
 * while it is. True until the wiki says the reader sees no Subject of it, so also while the answer is on its way and
 * when the wiki does not answer. Subjects on pages the reader may not read do not count.
 */
export function useSubjectsMayUseSchema( schemaName: () => string, asking: () => boolean ): ComputedRef<boolean> {
	const lookup = NeoWikiServices.getSubjectSummaryLookup();
	const readerSeesSubjects = ref<boolean | null>( null );
	let latestQuestion = 0;

	async function ask( schema: string ): Promise<void> {
		const question = ++latestQuestion;
		readerSeesSubjects.value = null;

		try {
			const { subjects } = await lookup.getSubjectSummaries(
				{ schema, search: '', sort: 'newest', direction: 'desc', cursor: null, limit: 1 },
			);

			if ( question === latestQuestion ) {
				readerSeesSubjects.value = subjects.length > 0;
			}
		} catch ( error ) {
			console.error( 'Failed to find out whether Subjects use the Schema:', error );
		}
	}

	watch( [ schemaName, asking ], ( [ schema, isAsking ] ) => {
		if ( isAsking ) {
			ask( schema );
		}
	}, { immediate: true } );

	return computed( (): boolean => readerSeesSubjects.value !== false );
}
