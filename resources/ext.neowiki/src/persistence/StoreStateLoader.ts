import { SubjectRepository } from '@/domain/SubjectRepository.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';
import type { ViewLookup } from '@/application/ViewLookup.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useViewStore } from '@/stores/ViewStore.ts';
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
		private readonly viewLookup: ViewLookup,
	) {
	}

	public async loadSubjectsAndSchemas( subjectIds: Set<string> ): Promise<void> {
		await Promise.all(
			Array.from( subjectIds ).map(
				( subjectId ) => this.loadForSubject( new SubjectId( subjectId ) ),
			),
		);
	}

	public async loadViews( viewNames: Set<string> ): Promise<void> {
		const viewStore = useViewStore();

		await Promise.all(
			Array.from( viewNames ).map( async ( viewName ) => {
				try {
					const view = await this.viewLookup.getView( viewName );
					viewStore.setView( viewName, view );
				} catch {
					// View not found or fetch failed — fallback to no-View behavior
				}
			} ),
		);
	}

	private async loadForSubject( subjectId: SubjectId ): Promise<void> {
		const subjectStore = useSubjectStore(); // TODO: inject
		const schemaStore = useSchemaStore(); // TODO: inject

		const subject = await this.subjectRepo.getSubject( subjectId );

		if ( subject !== undefined ) {
			subjectStore.setSubject( subject );

			const schema = await this.schemaRepo.getSchema( subject.getSchemaName() ); // TODO: handle not found
			schemaStore.setSchema( subject.getSchemaName(), schema );

			const referencedSubjects = await subject.getReferencedSubjects( this.subjectRepo );
			for ( const referencedSubject of referencedSubjects ) {
				subjectStore.setSubject( referencedSubject );
			}

			// TODO: we can just call await schemaStore.getOrFetchSchema().
			// Shall we remove the getOrFetch methods from the Stores?
			// If we keep them, we can just as well use them here.
			// Argument for removal: keep the Stores simple.
		}
	}

}
