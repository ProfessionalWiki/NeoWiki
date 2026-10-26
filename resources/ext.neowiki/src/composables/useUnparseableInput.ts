import { computed, ComputedRef, ref } from 'vue';
import { ValidationMessages } from '@wikimedia/codex';
import { violationStatus } from '@/composables/useServerViolations.ts';

interface UnparseableInput {
	fieldMessages: ComputedRef<ValidationMessages>;
	fieldStatus: ComputedRef<'default' | 'error' | 'warning'>;
	onUnparseableChange: ( message: string | null ) => void;
	unparseableInputMessage: () => string | null;
}

/**
 * For a value input built on NeoNativeInput: what its field shows, with the message for text the browser
 * cannot read in place of a server violation, which was raised against the value the backend was given
 * rather than what the field is showing. The user editing the field drops the violation.
 */
export function useUnparseableInput(
	validationMessages: ComputedRef<ValidationMessages>,
	clearServerViolation: () => void,
): UnparseableInput {
	const message = ref<string | null>( null );

	const fieldMessages = computed<ValidationMessages>( () =>
		message.value === null ? validationMessages.value : { error: message.value },
	);

	return {
		fieldMessages,
		// Derived from the merged messages rather than branched a second time: CdxField
		// only renders the message whose key equals the current status.
		fieldStatus: computed( () => violationStatus( fieldMessages.value ) ),
		onUnparseableChange: ( newMessage: string | null ): void => {
			message.value = newMessage;

			// Withdrawn for a new value from outside, which is no edit, or for an edit that made the
			// text readable, which the input reports itself.
			if ( newMessage !== null ) {
				clearServerViolation();
			}
		},
		unparseableInputMessage: (): string | null => message.value,
	};
}
