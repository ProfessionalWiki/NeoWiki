import { ref, Ref } from 'vue';

interface CloseConfirmation {
	confirmationOpen: Ref<boolean>;
	alternateConfirmationOpen: Ref<boolean>;
	requestClose: () => void;
	confirmClose: () => void;
	cancelClose: () => void;
	confirmAlternateClose: () => void;
	cancelAlternateClose: () => void;
}

export function useCloseConfirmation(
	hasChanged: Ref<boolean>,
	close: () => void,
	useAlternateConfirmation: Ref<boolean> = ref( false ),
): CloseConfirmation {
	const confirmationOpen = ref( false );
	const alternateConfirmationOpen = ref( false );

	function requestClose(): void {
		if ( !hasChanged.value ) {
			close();
			return;
		}

		if ( useAlternateConfirmation.value ) {
			alternateConfirmationOpen.value = true;
		} else {
			confirmationOpen.value = true;
		}
	}

	function confirmClose(): void {
		confirmationOpen.value = false;
		close();
	}

	function cancelClose(): void {
		confirmationOpen.value = false;
	}

	function confirmAlternateClose(): void {
		alternateConfirmationOpen.value = false;
		close();
	}

	function cancelAlternateClose(): void {
		alternateConfirmationOpen.value = false;
	}

	return {
		confirmationOpen,
		alternateConfirmationOpen,
		requestClose,
		confirmClose,
		cancelClose,
		confirmAlternateClose,
		cancelAlternateClose,
	};
}
