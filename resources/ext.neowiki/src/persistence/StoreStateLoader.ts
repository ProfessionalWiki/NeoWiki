import { SubjectRepository } from '@/domain/SubjectRepository.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';
import type { LayoutLookup } from '@/application/LayoutLookup.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { SubjectId } from '@/domain/SubjectId.ts';

/**
 * Potential improvements:
 * - avoid fetching the same schema multiple times
 * - batch requests (needs new API endpoint(s))
 */
export class StoreStateLoader {

	public constructor(
		private readonly subjectRepo: SubjectRepository,
		private readonly schemaRepo: SchemaRepository,
		private readonly layoutLookup: LayoutLookup,
	) {
	}

	/**
	 * Loads each Subject independently: one that does not load is skipped, so the Views of the
	 * others still get their data. A Subject the viewer may not read is answered as an absent one
	 * (ADR 27), and a failed request arrives the same way — neither may blank the whole page, so
	 * both are skipped, and the reason is logged for whoever has to tell them apart.
	 */
	public async loadSubjectsAndSchemas( subjectIds: Set<string> ): Promise<void> {
		await Promise.all(
			Array.from( subjectIds ).map( async ( subjectId ) => {
				try {
					await this.loadForSubject( new SubjectId( subjectId ) );
				} catch ( error ) {
					mw.log.warn( `NeoWiki: skipping Subject ${ subjectId }, which did not load:`, error );
				}
			} ),
		);
	}

	public async loadLayouts( layoutNames: Set<string> ): Promise<void> {
		const layoutStore = useLayoutStore();
		const epoch = layoutStore.mutationEpoch;

		await Promise.all(
			Array.from( layoutNames ).map( async ( layoutName ) => {
				try {
					const layout = await this.layoutLookup.getLayout( layoutName );
					if ( epoch === layoutStore.mutationEpoch ) {
						layoutStore.setLayout( layoutName, layout );
					}
				} catch {
					// Layout not found or fetch failed — fallback to no-Layout behavior
				}
			} ),
		);
	}

	private async loadForSubject( subjectId: SubjectId ): Promise<void> {
		const subjectStore = useSubjectStore(); // TODO: inject
		const schemaStore = useSchemaStore(); // TODO: inject
		const subjectEpoch = subjectStore.mutationEpoch;
		const schemaEpoch = schemaStore.mutationEpoch;

		// The repository bundles the requested Subject with the Subjects its
		// relations target, so storing them all avoids a re-fetch per relation.
		const { requestedSubject, referencedSubjects } =
			await this.subjectRepo.getSubjectWithReferencedSubjects( subjectId );
		// Read before anything is stored, so that a Subject whose Schema does not load is skipped
		// whole: a stored Subject with no Schema to render it by throws in the display instead.
		const schema = await this.schemaRepo.getSchema( requestedSubject.getSchemaName() );

		if ( subjectEpoch === subjectStore.mutationEpoch ) {
			subjectStore.setSubject( requestedSubject );
			for ( const subject of referencedSubjects ) {
				subjectStore.setSubject( subject );
			}
		}

		if ( schemaEpoch === schemaStore.mutationEpoch ) {
			schemaStore.setSchema( requestedSubject.getSchemaName(), schema );
		}
	}

}
