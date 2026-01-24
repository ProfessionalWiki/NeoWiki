import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { useOverflowDetection } from '@/composables/useOverflowDetection';
import { ref, nextTick, Ref } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';

// Mock @wikimedia/codex
const mockDimensions = ref( { width: 0, height: 0 } );
vi.mock( '@wikimedia/codex', () => ( {
	useResizeObserver: () => mockDimensions,
} ) );

enableAutoUnmount( afterEach );

describe( 'useOverflowDetection', () => {
	beforeEach( () => {
		mockDimensions.value = { width: 0, height: 0 };
	} );

	function mountComposable( composable: () => ReturnType<typeof useOverflowDetection> ): ReturnType<typeof useOverflowDetection> {
		let result: ReturnType<typeof useOverflowDetection>;
		mount( {
			setup() {
				result = composable();
				return () => {
					// Dummy render function
				};
			},
		} );
		// @ts-expect-error: result is assigned in setup
		return result;
	}

	const createMockElementRef = ( scrollHeight: number, clientHeight: number ): Ref<HTMLElement> => {
		const element = {
			scrollHeight,
			clientHeight,
		} as HTMLElement;
		return ref( element );
	};

	it( 'initializes with hasOverflow as false', () => {
		const elRef = ref<HTMLElement | null>( null );
		const result = mountComposable( () => useOverflowDetection( [ elRef ] ) );

		expect( result.hasOverflow.value ).toBe( false );
	} );

	it( 'detects overflow when scrollHeight > clientHeight', () => {
		const elRef = createMockElementRef( 200, 100 );
		const result = mountComposable( () => useOverflowDetection( [ elRef ] ) );

		result.checkOverflow();

		expect( result.hasOverflow.value ).toBe( true );
	} );

	it( 'does not detect overflow when scrollHeight <= clientHeight', () => {
		const elRef = createMockElementRef( 100, 100 );
		const result = mountComposable( () => useOverflowDetection( [ elRef ] ) );

		result.checkOverflow();

		expect( result.hasOverflow.value ).toBe( false );
	} );

	it( 'detects overflow if any of multiple elements overflow', () => {
		const el1Ref = createMockElementRef( 100, 100 );
		const el2Ref = createMockElementRef( 200, 100 );
		const result = mountComposable( () => useOverflowDetection( [ el1Ref, el2Ref ] ) );

		result.checkOverflow();

		expect( result.hasOverflow.value ).toBe( true );
	} );

	it( 'updates hasOverflow when resize occurs', async () => {
		const elementRef = createMockElementRef( 100, 100 );
		const result = mountComposable( () => useOverflowDetection( [ elementRef ] ) );

		expect( result.hasOverflow.value ).toBe( false );

		// Simulate content growth
		Object.defineProperty( elementRef.value, 'scrollHeight', { value: 200 } );
		// Simulate resize event
		mockDimensions.value = { width: 100, height: 100 };
		await nextTick();

		expect( result.hasOverflow.value ).toBe( true );
	} );

	it( 'handles ComponentPublicInstance refs', () => {
		const el = { scrollHeight: 200, clientHeight: 100 } as HTMLElement;
		const componentRef = ref( { $el: el } );
		const result = mountComposable( () => useOverflowDetection( [ componentRef ] ) );

		result.checkOverflow();

		expect( result.hasOverflow.value ).toBe( true );
	} );
} );
