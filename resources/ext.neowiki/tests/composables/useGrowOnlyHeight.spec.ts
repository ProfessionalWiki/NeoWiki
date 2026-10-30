import { describe, it, expect, beforeEach, vi } from 'vitest';
import { computed, nextTick, ref } from 'vue';
import { useGrowOnlyHeight } from '@/composables/useGrowOnlyHeight';

// jsdom resolves no layout, so the real observer never reports a height.
const observedHeight = ref<number | undefined>( undefined );

vi.mock( '@wikimedia/codex', async ( importOriginal ) => ( {
	...( await importOriginal<typeof import( '@wikimedia/codex' )>() ),
	useResizeObserver: () => computed( () => ( { width: 0, height: observedHeight.value } ) ),
} ) );

function grownHeight(): ReturnType<typeof useGrowOnlyHeight> {
	return useGrowOnlyHeight( ref( undefined ) );
}

async function measured( height: number ): Promise<void> {
	observedHeight.value = height;
	await nextTick();
}

describe( 'useGrowOnlyHeight', () => {

	beforeEach( () => {
		observedHeight.value = undefined;
	} );

	it( 'holds no height before the element is measured', () => {
		expect( grownHeight().tallest.value ).toBe( 0 );
	} );

	it( 'holds the height the element is measured at', async () => {
		const { tallest } = grownHeight();

		await measured( 300 );

		expect( tallest.value ).toBe( 300 );
	} );

	it( 'follows the element as it grows', async () => {
		const { tallest } = grownHeight();

		await measured( 300 );
		await measured( 500 );

		expect( tallest.value ).toBe( 500 );
	} );

	it( 'keeps the tallest height when the element measures shorter', async () => {
		const { tallest } = grownHeight();

		await measured( 500 );
		await measured( 300 );

		expect( tallest.value ).toBe( 500 );
	} );

	it( 'lets go of the height when reset', async () => {
		const { tallest, reset } = grownHeight();
		await measured( 500 );

		reset();

		expect( tallest.value ).toBe( 0 );
	} );

	it( 'holds a shorter height measured after a reset', async () => {
		const { tallest, reset } = grownHeight();
		await measured( 500 );

		reset();
		await measured( 300 );

		expect( tallest.value ).toBe( 300 );
	} );

} );
