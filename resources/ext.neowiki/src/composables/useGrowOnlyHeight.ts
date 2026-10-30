import { ref, watch, type Ref } from 'vue';
import { useResizeObserver } from '@wikimedia/codex';

/**
 * The tallest the element's border box has been since the last reset, in CSS pixels, for a host that
 * holds the element at that height so that it only ever grows. Zero until the element is measured.
 */
export function useGrowOnlyHeight( element: Ref<HTMLElement | undefined> ): {
	tallest: Readonly<Ref<number>>;
	reset: () => void;
} {
	const tallest = ref( 0 );
	const dimensions = useResizeObserver( element );

	watch( () => dimensions.value.height, ( height = 0 ) => {
		tallest.value = Math.max( tallest.value, height );
	} );

	function reset(): void {
		tallest.value = 0;
	}

	return { tallest, reset };
}
