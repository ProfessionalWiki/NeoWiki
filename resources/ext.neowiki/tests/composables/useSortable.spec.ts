import { describe, it, expect, vi, afterEach } from 'vitest';
import { useSortable } from '@/composables/useSortable';
import { Ref, ref } from 'vue';
import { enableAutoUnmount, mount } from '@vue/test-utils';

const mockDestroy = vi.fn();
const mockCreate = vi.fn<( container: any, options: any ) => any>( () => ( { destroy: mockDestroy } ) );

vi.mock( 'sortablejs', () => ( {
	default: {
		create: ( container: any, options: any ) => mockCreate( container, options ),
	},
} ) );

enableAutoUnmount( afterEach );

describe( 'useSortable', () => {

	function mountComposable( containerRef: Ref<HTMLElement | null>, onReorder: ( a: number, b: number ) => void ): void {
		mount( {
			setup() {
				useSortable( containerRef, {
					handle: '.handle',
					onReorder,
				} );
				return () => {
					// Dummy render function
				};
			},
		} );
	}

	it( 'creates a SortableJS instance on mount with a valid container', () => {
		const container = document.createElement( 'ul' );
		const containerRef = ref<HTMLElement | null>( container );
		const onReorder = vi.fn();

		mountComposable( containerRef, onReorder );

		expect( mockCreate ).toHaveBeenCalledWith( container, expect.objectContaining( {
			handle: '.handle',
			animation: 150,
		} ) );
	} );

	it( 'does not create a SortableJS instance when container is null', () => {
		mockCreate.mockClear();
		const containerRef = ref<HTMLElement | null>( null );
		const onReorder = vi.fn();

		mountComposable( containerRef, onReorder );

		expect( mockCreate ).not.toHaveBeenCalled();
	} );

	it( 'calls onReorder with old and new indices on drag end', () => {
		const container = document.createElement( 'ul' );
		const child0 = document.createElement( 'li' );
		const child1 = document.createElement( 'li' );
		const child2 = document.createElement( 'li' );
		container.append( child0, child1, child2 );

		const containerRef = ref<HTMLElement | null>( container );
		const onReorder = vi.fn();

		mountComposable( containerRef, onReorder );

		const lastCall = mockCreate.mock.calls[ mockCreate.mock.calls.length - 1 ] as any[];
		const onEndCallback = lastCall[ 1 ].onEnd;

		// Simulate SortableJS moving child0 to index 2 in the DOM
		container.removeChild( child0 );
		container.appendChild( child0 );

		onEndCallback( {
			item: child0,
			from: container,
			oldIndex: 0,
			newIndex: 2,
		} );

		expect( onReorder ).toHaveBeenCalledWith( 0, 2 );
	} );

	it( 'does not call onReorder when old and new indices are the same', () => {
		const container = document.createElement( 'ul' );
		const child0 = document.createElement( 'li' );
		container.append( child0 );

		const containerRef = ref<HTMLElement | null>( container );
		const onReorder = vi.fn();

		mountComposable( containerRef, onReorder );

		const lastCall = mockCreate.mock.calls[ mockCreate.mock.calls.length - 1 ] as any[];
		const onEndCallback = lastCall[ 1 ].onEnd;

		onEndCallback( {
			item: child0,
			from: container,
			oldIndex: 0,
			newIndex: 0,
		} );

		expect( onReorder ).not.toHaveBeenCalled();
	} );

} );
