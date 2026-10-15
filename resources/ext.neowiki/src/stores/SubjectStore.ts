import { defineStore } from 'pinia';
import { SubjectId } from '@/domain/SubjectId';
import { Subject } from '@/domain/Subject';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { Schema, SchemaName } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PageSubjects } from '@/domain/PageSubjects.ts';
import { SubjectViolation } from '@/domain/SubjectViolation.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import type { SubjectWriteResult } from '@/domain/SubjectRepository.ts';
import { isSubjectFirst } from '@/wikiMode.ts';
import { SubjectNotFoundError } from '@/persistence/SubjectNotFoundError.ts';

/**
 * A Subject created together with a page of its own: the page is the server's to title, so where
 * the Subject landed is part of the answer rather than something the caller already knows.
 */
export interface CreatedSubjectPage {
	subjectId: SubjectId;
	pageTitle: string;
	pageId: number;
}

/**
 * A Subject the creator is opened on because a relation points to it: it is created under this id,
 * with one of these Schemas.
 */
export interface SubjectCreatorTarget {
	subjectId: SubjectId;
	schemaNames: readonly string[];
}

/**
 * A Subject write answers with the Schema the Subject instantiates, so a display can render the
 * saved values even when the Schema gained a property out of band. That Schema is a server read
 * riding along with the write, not the write's own result, so it takes the read guard (ADR 30
 * rule 3): the caller snapshots the Schema epoch before awaiting and passes it here.
 */
function recordBundledSchema( schema: Schema | null, epochBeforeRequest: number ): void {
	const schemaStore = useSchemaStore();

	if ( schema !== null && epochBeforeRequest === schemaStore.mutationEpoch ) {
		schemaStore.setSchema( schema.getName(), schema );
	}
}

/**
 * The Dependent Subjects a write creates or replaces with the Subject it names; the wiki keeps the others that
 * Subject still reaches.
 */
export type DependentSubjects = readonly Subject[];

export const useSubjectStore = defineStore( 'subject', {
	state: () => ( {
		subjects: new Map<string, Subject>(),
		/**
		 * Ids a read looked up and found no Subject for: none has the id, or none this user may read.
		 */
		notFoundIds: new Set<string>(),
		subjectCreatorOpen: false,
		/** The Subject the creator is open on, where it was opened on a relation target. */
		subjectCreatorTarget: null as SubjectCreatorTarget | null,
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
		/**
		 * For a caller holding no listing that promises the Subject is there, such as a View whose
		 * Subject may have failed to load.
		 */
		findSubject: ( state ) => function ( id: SubjectId ): Subject | undefined {
			return state.subjects.get( id.text ) as Subject | undefined;
		},
		/**
		 * Whether a read found no Subject under this id. A Subject the registry holds under the id
		 * outranks this: it may have been created or read since.
		 */
		wasNotFound: ( state ) => function ( id: SubjectId ): boolean {
			return state.notFoundIds.has( id.text );
		},
	},
	actions: {
		setSubject( subject: Subject ): void { // TODO: just take Subject
			this.subjects.set( subject.getId().text, subject );
		},
		/**
		 * Stores Subjects read together with their relation targets, and the targets the read found no
		 * Subject for.
		 */
		setSubjectsWithTargets( subjects: Subject[], targets: Subject[], notFoundTargetIds: SubjectId[] ): void {
			[ ...subjects, ...targets ].forEach( ( subject ) => this.setSubject( subject ) );
			notFoundTargetIds.forEach( ( id ) => this.notFoundIds.add( id.text ) );
		},
		// Display-only read over the seeded registry: returns the page-payload /
		// own-write value when present and fetches only on a genuine miss (e.g. a
		// Subject picked from search that the page does not reference). Editors must
		// not use this — they read through the repositories (see ADR 30).
		async getOrFetchSubject( id: SubjectId ): Promise<Subject> {
			if ( !this.subjects.has( id.text ) ) {
				const epoch = this.mutationEpoch;
				let subject: Subject;

				try {
					subject = await NeoWikiExtension.getInstance().getSubjectRepository().getSubject( id );
				} catch ( error ) {
					if ( error instanceof SubjectNotFoundError ) {
						this.notFoundIds.add( id.text );
					}

					throw error;
				}

				if ( epoch === this.mutationEpoch ) {
					this.setSubject( subject );
				}
			}
			return this.getSubject( id );
		},
		async updateSubject( subject: Subject, dependentSubjects: DependentSubjects, comment?: string ): Promise<void> {
			const schemaEpoch = useSchemaStore().mutationEpoch;

			const result = await NeoWikiExtension.getInstance().getSubjectRepository().updateSubject(
				subject.getId(),
				subject.getLabel(),
				subject.getStatements(),
				dependentSubjects,
				comment,
			);

			this.recordWriteResult( result, schemaEpoch );

			if ( result.subject !== null ) {
				await this.reloadWrittenPage( result.subject.getPageIdentifiers().getPageId() );
			}
		},
		/**
		 * Writes a Subject the client built, under the id it already carries. The counterpart to
		 * updateSubject for one the wiki does not have yet.
		 *
		 * Where it lands is the wiki's mode (ADR 33): a page-first wiki stores it on the page given,
		 * a subject-first one gives it a page of its own and ignores that page.
		 */
		async createSubject( subject: Subject, pageId: number, dependentSubjects: DependentSubjects, comment?: string ): Promise<SubjectId> {
			if ( isSubjectFirst() ) {
				return ( await this.createSubjectOnOwnPage( subject, dependentSubjects, comment ) ).subjectId;
			}

			return this.createOtherSubject(
				pageId,
				subject.getLabel(),
				subject.getSchemaName(),
				subject.getStatements(),
				dependentSubjects,
				comment,
				subject.getId(),
			);
		},
		async deleteSubject( subjectId: SubjectId, comment?: string ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().deleteSubject( subjectId, comment );
			this.mutationEpoch++;

			// Deletion can cascade server-side (orphaned relation stubs are swept), so the listing is
			// re-read rather than patched locally.
			await this.dropFromRegistryOnceUnlisted( subjectId, 'deletion' );
		},
		/**
		 * Moves a Subject to another page. The Subject keeps its id and goes on existing, so unlike
		 * a deletion this only takes it out of the current page's listing. Its registry copy still
		 * goes: that copy carries the page it used to be on, and the next read fetches it with its
		 * new page context.
		 */
		async moveSubject( subjectId: SubjectId, targetPageId: number, makeMainSubject: boolean, comment?: string ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository()
				.moveSubject( subjectId, targetPageId, makeMainSubject, comment );
			this.mutationEpoch++;

			await this.dropFromRegistryOnceUnlisted( subjectId, 'a move' );
		},
		/**
		 * Takes a Subject the backend has already removed from the current page out of the registry,
		 * re-syncing the page listing first.
		 *
		 * Registry invariant: every id listed in pageSubjects must resolve via getSubject at every
		 * synchronous point, since SubjectsManagerPage's `subjects` computed maps the listing through
		 * that (throwing) getter on every render. So the registry entry may only go once pageSubjects
		 * no longer names the id; dropping it eagerly is what produced the live "Unknown subject"
		 * crash.
		 *
		 * The write it follows is already acknowledged, so a failed re-sync must not reject: that
		 * would raise an error for a write that committed. The row survives instead, rendering
		 * consistently against the stale listing, until the next successful refresh.
		 */
		async dropFromRegistryOnceUnlisted( subjectId: SubjectId, operation: string ): Promise<void> {
			if ( this.pageSubjects?.getSubject( subjectId ) !== undefined ) {
				const before = this.subjects.get( subjectId.text );

				try {
					await this.loadPageSubjects( this.pageSubjects.getPageId() );
				} catch ( error ) {
					console.error( `Failed to refresh page subjects after ${ operation }:`, error );
					return;
				}

				// loadPageSubjects has its own epoch guard (ADR 30 rule 3): a mutation landing while
				// the re-sync was in flight discards its write-back, leaving the stale listing that
				// still names this id. Leave the registry alone then too.
				if ( this.pageSubjects?.getSubject( subjectId ) !== undefined ) {
					return;
				}

				// A Subject this page still points at comes back among the re-sync's referenced
				// Subjects, carrying the page it is on now. That copy is what the next render needs,
				// so dropping the entry would throw away the read that just replaced it.
				if ( this.subjects.get( subjectId.text ) !== before ) {
					return;
				}
			}

			this.subjects.delete( subjectId.text );
		},
		async validateSubject( label: string | null, schemaName: SchemaName, statements: StatementList ): Promise<SubjectViolation[]> {
			return NeoWikiExtension.getInstance().getSubjectRepository().validateSubject( label, schemaName, statements );
		},
		async validateSubjectUpdate( id: SubjectId, label: string | null, statements: StatementList ): Promise<SubjectViolation[]> {
			return NeoWikiExtension.getInstance().getSubjectRepository().validateSubjectUpdate( id, label, statements );
		},
		async createMainSubject(
			pageId: number,
			label: string | null,
			schemaName: SchemaName,
			statements: StatementList,
			dependentSubjects: DependentSubjects,
			comment?: string,
			id?: SubjectId,
		): Promise<SubjectId> {
			const schemaEpoch = useSchemaStore().mutationEpoch;

			const result = await NeoWikiExtension.getInstance().getSubjectRepository().createMainSubject(
				pageId,
				label,
				schemaName,
				statements,
				dependentSubjects,
				comment,
				id,
			);

			this.recordWriteResult( result, schemaEpoch );
			await this.reloadWrittenPage( pageId );

			return result.subjectId;
		},
		async createOtherSubject(
			pageId: number,
			label: string | null,
			schemaName: SchemaName,
			statements: StatementList,
			dependentSubjects: DependentSubjects,
			comment?: string,
			id?: SubjectId,
		): Promise<SubjectId> {
			const schemaEpoch = useSchemaStore().mutationEpoch;

			const result = await NeoWikiExtension.getInstance().getSubjectRepository().createOtherSubject(
				pageId,
				label,
				schemaName,
				statements,
				dependentSubjects,
				comment,
				id,
			);

			this.recordWriteResult( result, schemaEpoch );
			await this.reloadWrittenPage( pageId );

			return result.subjectId;
		},

		async createSubjectOnOwnPage( subject: Subject, dependentSubjects: DependentSubjects, comment?: string ): Promise<CreatedSubjectPage> {
			return this.createSubjectPage(
				subject.getLabel(),
				subject.getSchemaName(),
				subject.getStatements(),
				dependentSubjects,
				comment,
				undefined,
				subject.getId(),
			);
		},

		/**
		 * Creates a Subject together with a page of its own, and reports where it landed: the page
		 * is the server's to title, so the caller learns its name only from the answer.
		 */
		async createSubjectPage(
			label: string | null,
			schemaName: SchemaName,
			statements: StatementList,
			dependentSubjects: DependentSubjects,
			comment?: string,
			pageTitle?: string,
			id?: SubjectId,
		): Promise<CreatedSubjectPage> {
			const schemaEpoch = useSchemaStore().mutationEpoch;

			const result = await NeoWikiExtension.getInstance().getSubjectRepository().createSubjectPage(
				label,
				schemaName,
				statements,
				dependentSubjects,
				comment,
				pageTitle,
				id,
			);

			this.recordWriteResult( result, schemaEpoch );
			await this.reloadWrittenPage( result.pageId );

			return { subjectId: result.subjectId, pageTitle: result.pageTitle, pageId: result.pageId };
		},

		/**
		 * The write answers with its Subject alone, so the page is read again for the Dependent Subjects
		 * it wrote and removed. A failed read must not reject: the write has committed.
		 */
		async reloadWrittenPage( pageId: number ): Promise<void> {
			try {
				await this.loadPageSubjects( pageId );
			} catch ( error ) {
				console.error( 'Failed to read the page\'s subjects again after a write:', error );
			}
		},

		/**
		 * What every Subject write does with its answer. The Subject recorded is the response's, not
		 * the one sent: only the server's copy carries the normalisation the write applied and the
		 * page a create settled on. A response without page context records nothing and leaves the
		 * previous copy in place.
		 */
		recordWriteResult( result: SubjectWriteResult, schemaEpochBeforeRequest: number ): void {
			this.mutationEpoch++;

			if ( result.subject !== null ) {
				this.setSubject( result.subject );
			}

			recordBundledSchema( result.schema, schemaEpochBeforeRequest );
		},

		openSubjectCreator(): void {
			this.subjectCreatorTarget = null;
			this.subjectCreatorOpen = true;
		},
		openSubjectCreatorFor( subjectId: SubjectId, schemaNames: readonly string[] ): void {
			this.subjectCreatorTarget = { subjectId, schemaNames };
			this.subjectCreatorOpen = true;
		},
		closeSubjectCreator(): void {
			this.subjectCreatorOpen = false;
		},

		/**
		 * Drops the Subjects of that page the read no longer lists. Replaces the page listing only where
		 * it is that page's or there is none yet.
		 */
		async loadPageSubjects( pageId: number ): Promise<void> {
			const schemaStore = useSchemaStore();
			const epoch = this.mutationEpoch;
			const schemaEpoch = schemaStore.mutationEpoch;
			const result = await NeoWikiExtension.getInstance().getSubjectRepository().getPageSubjects( pageId );

			if ( epoch === this.mutationEpoch ) {
				const listedIds = new Set( result.pageSubjects.getSubjects().map( ( subject ) => subject.getId().text ) );

				for ( const [ id, subject ] of this.subjects ) {
					if ( subject.getPageIdentifiers().getPageId() === pageId && !listedIds.has( id ) ) {
						this.subjects.delete( id );
					}
				}

				if ( this.pageSubjects === null || this.pageSubjects.getPageId() === pageId ) {
					this.pageSubjects = result.pageSubjects;
				}

				this.setSubjectsWithTargets( result.pageSubjects.getSubjects(), result.referencedSubjects, result.notFoundTargetIds );
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
			otherSubjectIds: SubjectId[],
			comment?: string,
		): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().setSubjectsOrdering( pageId, mainSubjectId, otherSubjectIds, comment );
			this.mutationEpoch++;
			await this.loadPageSubjects( pageId );
		},
	},
} );
