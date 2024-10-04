import { defineStore } from 'pinia';
import { SubjectId } from '@neo/domain/SubjectId';
import { Subject } from '@neo/domain/Subject';
import { NeoWikiExtension } from '@/NeoWikiExtension';

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
		}
	}
} );
