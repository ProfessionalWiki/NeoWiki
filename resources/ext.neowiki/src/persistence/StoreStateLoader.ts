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

	public async loadSubjectsAndSchemas( subjectIds: Set<string> ): Promise<void> {
		await Promise.all(
			Array.from( subjectIds ).map(
				( subjectId ) => this.loadForSubject( new SubjectId( subjectId ) ),
			),
		);
	}

	public async loadLayouts( layoutNames: Set<string> ): Promise<void> {
		const layoutStore = useLayoutStore();

		await Promise.all(
			Array.from( layoutNames ).map( async ( layoutName ) => {
				try {
					const layout = await this.layoutLookup.getLayout( layoutName );
					layoutStore.setLayout( layoutName, layout );
				} catch {
					// Layout not found or fetch failed — fallback to no-Layout behavior
				}
			} ),
		);
	}

	private async loadForSubject( subjectId: SubjectId ): Promise<void> {
		// The repository bundles the requested Subject with the Subjects its
		// relations target, so storing them all avoids a re-fetch per relation.
		const subjects = await this.subjectRepo.getSubjectWithReferencedSubjects( subjectId );
		const requestedSubject = subjects.get( subjectId );

		if ( requestedSubject === undefined ) {
			return;
		}

		const subjectStore = useSubjectStore(); // TODO: inject
		for ( const subject of subjects ) {
			subjectStore.setSubject( subject );
		}

		const schemaStore = useSchemaStore(); // TODO: inject
		const schema = await this.schemaRepo.getSchema( requestedSubject.getSchemaName() ); // TODO: handle not found
		schemaStore.setSchema( requestedSubject.getSchemaName(), schema );
	}

}
