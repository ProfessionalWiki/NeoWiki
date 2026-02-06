import { ref, Ref } from 'vue';

interface ChangeDetection {
	hasChanged: Ref<boolean>;
	markChanged: () => void;
	resetChanged: () => void;
}

export function useChangeDetection(): ChangeDetection {
	const hasChanged = ref( false );

	function markChanged(): void {
		hasChanged.value = true;
	}

	function resetChanged(): void {
		hasChanged.value = false;
	}

	return {
		hasChanged,
		markChanged,
		resetChanged,
	};
}
