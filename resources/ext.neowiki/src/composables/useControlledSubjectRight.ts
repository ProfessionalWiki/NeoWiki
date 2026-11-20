import { computed, ref, type Ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

export interface ControlledSubjectRight {
	/** False until the wiki has answered. */
	canEditControlledSubjects: Readonly<Ref<boolean>>;
	/** Whether to offer adding, changing and removing a Schema's Subjects, given whether they are controlled. */
	mayEditSubjects: ( subjectsAreControlled: boolean ) => boolean;
	whenAnswered: () => Promise<boolean>;
}

/**
 * Whether to offer adding, changing and removing Controlled Subjects. The wiki is asked when the answer is first read.
 */
export function useControlledSubjectRight(): ControlledSubjectRight {
	const hints = NeoWikiServices.getSubjectPermissionHints();
	const allowed = ref( false );
	let answer: Promise<boolean> | undefined;

	function whenAnswered(): Promise<boolean> {
		answer ??= hints.canEditControlledSubjects().then(
			( value ) => {
				allowed.value = value;
				return value;
			},
			( error ) => {
				console.error( 'Failed to check the permission to edit Controlled Subjects:', error );
				return false;
			},
		);

		return answer;
	}

	const canEditControlledSubjects = computed( (): boolean => {
		whenAnswered();
		return allowed.value;
	} );

	return {
		canEditControlledSubjects,
		mayEditSubjects: ( subjectsAreControlled ) => !subjectsAreControlled || canEditControlledSubjects.value,
		whenAnswered,
	};
}
