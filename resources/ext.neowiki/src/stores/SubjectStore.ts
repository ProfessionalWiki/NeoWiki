import { defineStore } from 'pinia';
import { SubjectId } from '@/domain/SubjectId';
import { Subject } from '@/domain/Subject';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { SchemaName } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';
import { PageSubjects } from '@/domain/PageSubjects.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
export const useSubjectStore = defineStore( 'subject', {
	state: () => ( {
		subjects: new Map<string, Subject>(),
		subjectCreatorOpen: false,
		pageSubjects: null as PageSubjects | null,
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
		async fetchSubject( id: SubjectId ): Promise<void> {
			const subject = await NeoWikiExtension.getInstance().getSubjectRepository().getSubject( id );
			this.setSubject( subject );
		},
		async getOrFetchSubject( id: SubjectId ): Promise<Subject> {
			if ( !this.subjects.has( id.text ) ) {
				await this.fetchSubject( id );
			}
			return this.getSubject( id );
		},
		async updateSubject( subject: Subject, comment?: string ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().updateSubject( subject.getId(), subject.getLabel(), subject.getStatements(), comment );
			this.setSubject( subject );
		},
		async deleteSubject( subjectId: SubjectId, comment?: string ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().deleteSubject( subjectId, comment );
			this.subjects.delete( subjectId.text );
		},
		async createMainSubject( pageId: number, label: string, schemaName: SchemaName, statements: StatementList, comment?: string ): Promise<SubjectId> {
			const subjectId = await NeoWikiExtension.getInstance().getSubjectRepository().createMainSubject(
				pageId,
				label,
				schemaName,
				statements,
				comment,
			);

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
			const repository = NeoWikiExtension.getInstance().getSubjectRepository();
			const result = await repository.getPageSubjects( pageId );

			this.pageSubjects = result.pageSubjects;

			for ( const subject of result.pageSubjects.getSubjects() ) {
				this.setSubject( subject );
			}
			for ( const subject of result.referencedSubjects ) {
				this.setSubject( subject );
			}

			const schemaStore = useSchemaStore();
			for ( const schema of result.schemas ) {
				schemaStore.setSchema( schema.getName(), schema );
			}
		},

		async setPageMainSubject( pageId: number, subjectId: SubjectId | null, comment?: string ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().setMainSubject( pageId, subjectId, comment );
			await this.loadPageSubjects( pageId );
		},
	},
} );
