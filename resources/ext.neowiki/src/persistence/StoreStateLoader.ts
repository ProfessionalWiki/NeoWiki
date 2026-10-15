import { SubjectRepository } from '@/domain/SubjectRepository.ts';
import { SchemaRepository } from '@/application/SchemaRepository.ts';
import type { LayoutLookup } from '@/application/LayoutLookup.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { SubjectId } from '@/domain/SubjectId.ts';
import type { Schema, SchemaName } from '@/domain/Schema.ts';
import type { Subject } from '@/domain/Subject.ts';
import { isNamedAfterHostSubject } from '@/domain/dependentSubjects.ts';

/**
 * Shared by the Views of one page, so that each Schema is read once, with the Schema store's epoch from before the
 * first read.
 */
interface SchemaReads {
	reads: Map<SchemaName, Promise<Schema>>;
	epoch: number;
}

/**
 * Potential improvements:
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
	 * Loads each Subject independently and never rejects: one that does not load is logged, so
	 * neither a denied read nor a failed request blanks the page's other Views. A Subject the
	 * viewer may not read is answered as an absent one (ADR 27). A Subject whose Schema does not
	 * load stays in the Subject store without it.
	 *
	 * Only the Subjects in subjectIdsWithLayout get the Schemas of their Dependent Subjects loaded: a View shows a
	 * Dependent Subject only where its Layout names the property pointing at it.
	 */
	public async loadSubjectsAndSchemas( subjectIds: Set<string>, subjectIdsWithLayout: Set<string> ): Promise<void> {
		const schemaReads: SchemaReads = { reads: new Map(), epoch: useSchemaStore().mutationEpoch };

		await Promise.all(
			Array.from( subjectIds ).map( async ( subjectId ) => {
				try {
					await this.loadForSubject( new SubjectId( subjectId ), subjectIdsWithLayout.has( subjectId ), schemaReads );
				} catch ( error ) {
					console.warn( `NeoWiki: skipping Subject ${ subjectId }, which did not load:`, error );
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

	/**
	 * A Schema of a Dependent Subject that does not load leaves its Dependent Subjects linked rather than failing the
	 * View.
	 */
	private async loadForSubject( subjectId: SubjectId, withLayout: boolean, schemaReads: SchemaReads ): Promise<void> {
		const subjectStore = useSubjectStore(); // TODO: inject
		const subjectEpoch = subjectStore.mutationEpoch;

		// The repository bundles the requested Subject with the Subjects its relations target, and
		// those its Dependent Subjects target, so storing them all avoids a re-fetch per relation.
		const { requestedSubject, referencedSubjects, notFoundTargetIds } =
			await this.subjectRepo.getSubjectWithReferencedSubjects( subjectId );

		if ( subjectEpoch === subjectStore.mutationEpoch ) {
			subjectStore.setSubjectsWithTargets( [ requestedSubject ], referencedSubjects, notFoundTargetIds );
		}

		await Promise.all( [
			this.loadSchema( requestedSubject.getSchemaName(), schemaReads ),
			...( withLayout ? this.schemaNamesOfDependentSubjects( referencedSubjects ) : [] ).map( ( schemaName ) =>
				this.loadSchema( schemaName, schemaReads ).catch( ( error ) => {
					console.warn( `NeoWiki: showing the ${ schemaName } Subjects of ${ subjectId.text } as links:`, error );
				} ) ),
		] );
	}

	private schemaNamesOfDependentSubjects( subjects: Subject[] ): SchemaName[] {
		return [ ...new Set(
			subjects.filter( isNamedAfterHostSubject ).map( ( subject ) => subject.getSchemaName() ),
		) ];
	}

	private async loadSchema( schemaName: SchemaName, schemaReads: SchemaReads ): Promise<void> {
		const schemaStore = useSchemaStore(); // TODO: inject
		let read = schemaReads.reads.get( schemaName );

		if ( read === undefined ) {
			read = this.schemaRepo.getSchema( schemaName );
			schemaReads.reads.set( schemaName, read );
		}

		const schema = await read;

		if ( schemaReads.epoch === schemaStore.mutationEpoch ) {
			schemaStore.setSchema( schemaName, schema );
		}
	}

}
