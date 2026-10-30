import { describe, it, expect, beforeEach, vi } from 'vitest';
import { computed, defineComponent, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { useGrowOnlyHeight } from '@/composables/useGrowOnlyHeight';

// jsdom resolves no layout, so the real observer never reports a height.
const observedHeight = ref<number | undefined>( undefined );

vi.mock( '@wikimedia/codex', async ( importOriginal ) => ( {
	...( await importOriginal<typeof import( '@wikimedia/codex' )>() ),
	useResizeObserver: () => computed( () => ( { width: 0, height: observedHeight.value } ) ),
} ) );

// The composable needs an owning scope: its resize observer is bound to one.
function withElement(): ReturnType<typeof useGrowOnlyHeight> {
	let api!: ReturnType<typeof useGrowOnlyHeight>;

	mount( defineComponent( {
		template: '<div />',
		setup() {
			api = useGrowOnlyHeight( ref( undefined ) );
			return {};
		},
	} ) );

	return api;
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
		expect( withElement().tallest.value ).toBeNull();
	} );

	it( 'holds the height the element is measured at', async () => {
		const { tallest } = withElement();

		await measured( 300 );

		expect( tallest.value ).toBe( 300 );
	} );

	it( 'follows the element as it grows', async () => {
		const { tallest } = withElement();

		await measured( 300 );
		await measured( 500 );

		expect( tallest.value ).toBe( 500 );
	} );

	it( 'keeps the tallest height when the element measures shorter', async () => {
		const { tallest } = withElement();

		await measured( 500 );
		await measured( 300 );

		expect( tallest.value ).toBe( 500 );
	} );

	it( 'lets go of the height when reset', async () => {
		const { tallest, reset } = withElement();
		await measured( 500 );

		reset();

		expect( tallest.value ).toBeNull();
	} );

	it( 'holds a shorter height measured after a reset', async () => {
		const { tallest, reset } = withElement();
		await measured( 500 );

		reset();
		await measured( 300 );

		expect( tallest.value ).toBe( 300 );
	} );

} );
