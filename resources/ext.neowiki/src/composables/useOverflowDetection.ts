import { computed, onMounted, Ref, ref, unref, watch } from 'vue';
import { useResizeObserver } from '@wikimedia/codex';

export function useOverflowDetection(
	elements: Ref<HTMLElement | { $el: HTMLElement } | null>[],
): {
		hasOverflow: Ref<boolean>;
		checkOverflow: () => void;
	} {
	const hasOverflow = ref( false );

	function getElement( elementRef: Ref<HTMLElement | { $el: HTMLElement } | null> ): HTMLElement | null {
		const element = unref( elementRef );
		return element && '$el' in element ? element.$el : element;
	}

	function checkOverflow(): void {
		hasOverflow.value = elements.some( ( elementRef ) => {
			const el = getElement( elementRef );

			if ( !el ) {
				return false;
			}

			return el.scrollHeight > el.clientHeight;
		} );
	}

	elements.forEach( ( elementRef ) => {
		const domElement = computed( () => {
			const val = unref( elementRef );
			return ( val && '$el' in val ? val.$el : val ) || undefined;
		} );

		const dimensions = useResizeObserver( domElement );

		watch( dimensions, () => {
			checkOverflow();
		} );
	} );

	watch( elements, () => {
		checkOverflow();
	} );

	onMounted( () => {
		checkOverflow();
	} );

	return {
		hasOverflow,
		checkOverflow,
	};
}
