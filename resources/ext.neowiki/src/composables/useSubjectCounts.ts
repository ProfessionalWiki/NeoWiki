import { computed, type ComputedRef, shallowRef } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { areSubjectCountsAvailable } from '@/subjectCountAvailability.ts';

export interface SubjectCounts {
	/** False until counts arrive, and for good where the reader sees none or they could not be loaded. */
	subjectCountsKnown: ComputedRef<boolean>;
	/** Null while the counts are not known. */
	subjectCountOf: ( schemaName: string ) => number | null;
	loadSubjectCounts: () => Promise<void>;
}

export function useSubjectCounts(): SubjectCounts {
	const lookup = NeoWikiServices.getSubjectCountLookup();
	const counts = shallowRef<Map<string, number> | null>( null );

	async function loadSubjectCounts(): Promise<void> {
		if ( !areSubjectCountsAvailable() ) {
			return;
		}

		try {
			counts.value = await lookup.getSubjectCounts();
		} catch ( error ) {
			console.error( 'Failed to load subject counts:', error );
		}
	}

	function subjectCountOf( schemaName: string ): number | null {
		return counts.value === null ? null : counts.value.get( schemaName ) ?? 0;
	}

	return {
		subjectCountsKnown: computed( () => counts.value !== null ),
		subjectCountOf,
		loadSubjectCounts,
	};
}
