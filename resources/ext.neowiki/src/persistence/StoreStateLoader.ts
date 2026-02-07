import { SubjectRepository } from '@/domain/SubjectRepository.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
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
	) {
	}

	public async loadSubjectsAndSchemas( subjectIds: Set<string> ): Promise<void> {
		await Promise.all(
			Array.from( subjectIds ).map(
				( subjectId ) => this.loadForSubject( new SubjectId( subjectId ) ),
			),
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
