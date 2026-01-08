import { defineStore } from 'pinia';
import { SubjectId } from '@/domain/SubjectId';
import { Subject } from '@/domain/Subject';
import { NeoWikiExtension } from '@/NeoWikiExtension';
import { SchemaName } from '@/domain/Schema.ts';
import { StatementList } from '@/domain/StatementList.ts';
import { PageIdentifiers } from '@/domain/PageIdentifiers.ts';
import { SubjectWithContext } from '@/domain/SubjectWithContext.ts';

export const useSubjectStore = defineStore( 'subject', {
	state: () => ( {
		subjects: new Map<string, Subject>()
	} ),
	getters: {
		getSubject: ( state ) => function ( id: SubjectId ): Subject | undefined {
			const subject = state.subjects.get( id.text );

			if ( subject === undefined ) {
				throw new Error( 'Unknown subject: ' + id.text );
			}

			return subject as Subject;
		}
	},
	actions: {
		setSubject( subject: Subject ): void { // TODO: just take Subject
			this.subjects.set( subject.getId().text, subject );
		},
		async fetchSubject( id: SubjectId ): Promise<void> {
			const subject = await NeoWikiExtension.getInstance().getSubjectRepository().getSubject( id );
			this.setSubject( subject );
		},
		async getOrFetchSubject( id: SubjectId ): Promise<Subject | undefined> {
			if ( !this.subjects.has( id.text ) ) {
				await this.fetchSubject( id );
			}
			return this.getSubject( id );
		},
		async updateSubject( subject: Subject ): Promise<void> {
			await NeoWikiExtension.getInstance().getSubjectRepository().updateSubject( subject.getId(), subject.getLabel(), subject.getStatements() );
			this.setSubject( subject );
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
