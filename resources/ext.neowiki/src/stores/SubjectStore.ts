import { defineStore } from 'pinia';
import { SubjectId } from '@/domain/SubjectId';
import { Subject } from '@/domain/Subject';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { SchemaName } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { PageSubjects } from '@/domain/PageSubjects.ts';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
export const useSubjectStore = defineStore( 'subject', {
	state: () => ( {
		subjects: new Map<string, Subject>(),
		subjectCreatorOpen: false,
		pageSubjects: null as PageSubjects | null,
		mutationEpoch: 0, // See SchemaStore.mutationEpoch — same guard contract.
	} ),
	getters: {
		getSubject: ( state ) => function ( id: SubjectId ): Subject {
			const subject = state.subjects.get( id.text );

			if ( subject === undefined ) {
				throw new Error( 'Unknown subject: ' + id.text );
			}

			return subject as Subject;
		},
	},
	actions: {
		setSubject( subject: Subject ): void { // TODO: just take Subject
			this.subjects.set( subject.getId().text, subject );
		},
		// A resolved call may have recorded nothing: the epoch guard below discards the write-back
		// when a mutation landed mid-flight, leaving the registry unchanged. Callers must read the
		// result via getSubject afterwards and handle a miss (getSubject throws), rather than
		// assuming this call alone guarantees fresh data is present.
		async fetchSubject( id: SubjectId ): Promise<void> {
			const epoch = this.mutationEpoch;
			const subject = await NeoWikiExtension.getInstance().getSubjectRepository().getSubject( id );
			if ( epoch !== this.mutationEpoch ) {
				return;
			}
			this.setSubject( subject );
		},
		// Display-only read over the seeded registry: returns the page-payload /
		// own-write value when present and fetches only on a genuine miss (e.g. a
		// Subject picked from search that the page does not reference). Editors must
		// not use this — always fetch fresh before opening an editor (see ADR 30).
		async getOrFetchSubject( id: SubjectId ): Promise<Subject> {
			if ( !this.subjects.has( id.text ) ) {
				await this.fetchSubject( id );
			}
			return this.getSubject( id );
		},
		async updateSubject( subject: Subject, comment?: string ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().updateSubject( subject.getId(), subject.getLabel(), subject.getStatements(), comment );
			this.mutationEpoch++;
			this.setSubject( subject );
		},
		async deleteSubject( subjectId: SubjectId, comment?: string ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().deleteSubject( subjectId, comment );
			this.mutationEpoch++;

			// Registry invariant: every id listed in pageSubjects must resolve via getSubject at
			// every synchronous point, since SubjectsManagerPage's `subjects` computed maps the
			// listing through that (throwing) getter on every render. The registry removal below
			// must therefore wait until pageSubjects itself no longer names this id; deleting
			// eagerly here (before the re-sync below lands) is what produced the live
			// "Unknown subject" crash.
			//
			// Deletion can cascade server-side (orphaned relation stubs are swept), so re-sync the
			// page listing from the backend rather than patching it locally.
			if ( this.pageSubjects?.getSubject( subjectId ) !== undefined ) {
				try {
					// The DELETE above is already acknowledged by the backend, so a failure
					// here must not reject this action — that would surface a delete-error
					// toast for a delete that already committed. Instead we return without
					// touching the registry: the ghost row survives (rendering consistently
					// against the still-stale pageSubjects) until the next successful refresh.
					// A retry-delete will then fail against the already-missing subject with an
					// error toast — an accepted trade-off over a broken render.
					await this.loadPageSubjects( this.pageSubjects.getPageId() );
				} catch ( error ) {
					console.error( 'Failed to refresh page subjects after deletion:', error );
					return;
				}

				// loadPageSubjects has its own epoch guard (ADR 30 rule 3): if another mutation
				// landed while the re-sync was in flight, its write-back was discarded and
				// pageSubjects is still the stale, pre-delete listing that names this id. Leave
				// the registry alone in that case too, for the same invariant reason as the
				// catch above; the next successful refresh catches up.
				if ( this.pageSubjects?.getSubject( subjectId ) !== undefined ) {
					return;
				}
			}

			this.subjects.delete( subjectId.text );
		},
		async validateSubject( label: string, schemaName: SchemaName, statements: StatementList ): Promise<SubjectViolation[]> {
			return NeoWikiExtension.getInstance().getSubjectRepository().validateSubject( label, schemaName, statements );
		},
		async validateSubjectUpdate( id: SubjectId, label: string, statements: StatementList ): Promise<SubjectViolation[]> {
			return NeoWikiExtension.getInstance().getSubjectRepository().validateSubjectUpdate( id, label, statements );
		},
		async createMainSubject( pageId: number, label: string, schemaName: SchemaName, statements: StatementList, comment?: string ): Promise<SubjectId> {
			const subjectId = await NeoWikiExtension.getInstance().getSubjectRepository().createMainSubject(
				pageId,
				label,
				schemaName,
				statements,
				comment,
			);
			this.mutationEpoch++;

			this.setSubject(
				new SubjectWithContext(
					subjectId,
					label,
					schemaName,
					statements,
					// FIXME: 'page-title', assuming we need to actually set the Subject here.
					// Perhaps we are better off getting the entire thing from the backend.
					// Maybe the backend should respond with the entire thing instead of just the ID.
					// Getting the subject from the backend is safer, since we avoid inconsistencies in
					// case normalization happened or someone else edited as well.
					new PageIdentifiers( pageId, 'page-title' ),
				),
			);
			return subjectId;
		},
		async createChildSubject( pageId: number, label: string, schemaName: SchemaName, statements: StatementList, comment?: string ): Promise<SubjectId> {
			const subjectId = await NeoWikiExtension.getInstance().getSubjectRepository().createChildSubject(
				pageId,
				label,
				schemaName,
				statements,
				comment,
			);
			this.mutationEpoch++;

			this.setSubject(
				new SubjectWithContext(
					subjectId,
					label,
					schemaName,
					statements,
					new PageIdentifiers( pageId, 'page-title' ),
				),
			);
			return subjectId;
		},

		openSubjectCreator(): void {
			this.subjectCreatorOpen = true;
		},
		closeSubjectCreator(): void {
			this.subjectCreatorOpen = false;
		},

		async loadPageSubjects( pageId: number ): Promise<void> {
			const schemaStore = useSchemaStore();
			const epoch = this.mutationEpoch;
			const schemaEpoch = schemaStore.mutationEpoch;
			const result = await NeoWikiExtension.getInstance().getSubjectRepository().getPageSubjects( pageId );

			if ( epoch === this.mutationEpoch ) {
				this.pageSubjects = result.pageSubjects;

				for ( const subject of result.pageSubjects.getSubjects() ) {
					this.setSubject( subject );
				}
				for ( const subject of result.referencedSubjects ) {
					this.setSubject( subject );
				}
			}

			if ( schemaEpoch === schemaStore.mutationEpoch ) {
				for ( const schema of result.schemas ) {
					schemaStore.setSchema( schema.getName(), schema );
				}
			}
		},

		async setPageMainSubject( pageId: number, subjectId: SubjectId | null, comment?: string ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().setMainSubject( pageId, subjectId, comment );
			this.mutationEpoch++;
			await this.loadPageSubjects( pageId );
		},

		async setPageSubjectsOrdering(
			pageId: number,
			mainSubjectId: SubjectId | null,
			childSubjectIds: SubjectId[],
			comment?: string,
		): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().setSubjectsOrdering( pageId, mainSubjectId, childSubjectIds, comment );
			this.mutationEpoch++;
			await this.loadPageSubjects( pageId );
		},
	},
} );
