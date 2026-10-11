import { ref, Ref } from 'vue';

interface CloseConfirmation {
	confirmationOpen: Ref<boolean>;
	requestClose: () => void;
	confirmClose: () => void;
	cancelClose: () => void;
}

export function useCloseConfirmation(
	hasChanged: Ref<boolean>,
	close: () => void,
): CloseConfirmation {
	const confirmationOpen = ref( false );

	function requestClose(): void {
		if ( !hasChanged.value ) {
			close();
			return;
		}

		confirmationOpen.value = true;
	}

	function confirmClose(): void {
		confirmationOpen.value = false;
		close();
	}

	function cancelClose(): void {
		confirmationOpen.value = false;
	}

	return {
		confirmationOpen,
		requestClose,
		confirmClose,
		cancelClose,
	};
}
