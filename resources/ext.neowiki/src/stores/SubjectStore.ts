import { defineStore } from 'pinia';
import { SubjectId } from '@neo/domain/SubjectId';
import { Subject } from '@neo/domain/Subject';
import { createExampleSubjects } from '@/ExampleData.ts';

export const useSubjectStore = defineStore( 'subject', {
	state: () => ( {
		subjects: createExampleSubjects()
	} ),
	getters: {
		getSubject: ( state ) => function ( id: SubjectId ): Subject {
			return state.subjects.get( id.text ) as Subject;
		}
	}
} );
