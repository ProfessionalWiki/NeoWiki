import { ref, watch, type Ref } from 'vue';
import { useResizeObserver } from '@wikimedia/codex';

/**
 * The tallest the element's border box has been since the last reset, in CSS pixels, for a host that
 * holds the element at that height so that it only ever grows. Null until the element is measured.
 */
export function useGrowOnlyHeight( element: Ref<HTMLElement | undefined> ): {
	tallest: Readonly<Ref<number | null>>;
	reset: () => void;
} {
	const tallest = ref<number | null>( null );
	const dimensions = useResizeObserver( element );

	watch( () => dimensions.value.height, ( height ) => {
		if ( height !== undefined && height > ( tallest.value ?? 0 ) ) {
			tallest.value = height;
		}
	} );

	function reset(): void {
		tallest.value = null;
	}

	return { tallest, reset };
}
