import { defineStore } from 'pinia';
import { SubjectId } from '@neo/domain/SubjectId';
import { Subject } from '@neo/domain/Subject';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { SchemaName } from '@neo/domain/Schema.ts';
import { StatementList } from '@neo/domain/StatementList.ts';
import { PageIdentifiers } from '@neo/domain/PageIdentifiers.ts';
import { SubjectWithContext } from '@neo/domain/SubjectWithContext.ts';

export const useSubjectStore = defineStore( 'subject', {
	state: () => ( {
		subjects: new Map<string, Subject>()
	} ),
	getters: {
		getSubject: ( state ) => function ( id: SubjectId ): Subject {
			const subject = state.subjects.get( id.text );
			if ( subject === undefined ) {
				throw new Error( 'Unknown subject: ' + id.text );
			}

			return subject as Subject;
		}
	},
	actions: {
		setSubject( id: SubjectId, subject: Subject ): void {
			this.subjects.set( id.text, subject );
		},
		async fetchSubject( id: SubjectId ): Promise<void> {
			const subject = await NeoWikiExtension.getInstance().getSubjectRepository().getSubject( id );
			this.setSubject( id, subject );
		},
		async getOrFetchSubject( id: SubjectId ): Promise<Subject> {
			if ( !this.subjects.has( id.text ) ) {
				await this.fetchSubject( id );
			}
			return this.getSubject( id );
		},
		async updateSubject( subject: Subject ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().updateSubject( subject.getId(), subject.getLabel(), subject.getStatements() );
			this.setSubject( subject.getId(), subject );
		},
		async deleteSubject( subjectId: SubjectId ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().deleteSubject( subjectId );
			this.subjects.delete( subjectId.text );
		},
		async createMainSubject( pageId: number, label: string, schemaName: SchemaName, statements: StatementList ): Promise<SubjectId> {
			const subjectId = await NeoWikiExtension.getInstance().getSubjectRepository().createMainSubject(
				pageId,
				label,
				schemaName,
				statements
			);

			this.setSubject(
				subjectId,
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
					new PageIdentifiers( pageId, 'page-title' )
				)
			);
			return subjectId;
		}
	}
} );
