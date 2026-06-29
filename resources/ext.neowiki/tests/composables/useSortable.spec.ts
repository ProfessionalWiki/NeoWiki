import { describe, it, expect, vi, afterEach, beforeEach } from 'vitest';
import { useSortable, UseSortableOptions } from '@/composables/useSortable';
import { Ref, ref } from 'vue';
import { enableAutoUnmount, flushPromises, mount } from '@vue/test-utils';

const mockDestroy = vi.fn();
const mockCreate = vi.fn<( container: any, options: any ) => any>( () => ( { destroy: mockDestroy } ) );

vi.mock( 'sortablejs', () => ( {
	default: {
		create: ( container: any, options: any ) => mockCreate( container, options ),
	},
} ) );

enableAutoUnmount( afterEach );

beforeEach( () => {
	mockCreate.mockClear();
	mockDestroy.mockClear();
} );

function mountComposable( containerRef: Ref<HTMLElement | null>, options: UseSortableOptions ): void {
	mount( {
		setup() {
			useSortable( containerRef, options );
			return () => null;
		},
	} );
}

function lastOptions(): any {
	const calls = mockCreate.mock.calls;
	return calls[ calls.length - 1 ][ 1 ];
}

describe( 'useSortable', () => {

	it( 'creates a SortableJS instance on mount with a valid container', async () => {
		const container = document.createElement( 'ul' );
		const containerRef = ref<HTMLElement | null>( container );

		mountComposable( containerRef, { handle: '.handle', onReorder: vi.fn() } );
		await flushPromises();

		expect( mockCreate ).toHaveBeenCalledWith( container, expect.objectContaining( {
			handle: '.handle',
			animation: 150,
		} ) );
	} );

	it( 'does not create a SortableJS instance when container is null', () => {
		const containerRef = ref<HTMLElement | null>( null );

		mountComposable( containerRef, { onReorder: vi.fn() } );

		expect( mockCreate ).not.toHaveBeenCalled();
	} );

	it( 'omits sort, group, and draggable from sortablejs options when not provided (preserves sortablejs defaults)', () => {
		const containerRef = ref<HTMLElement | null>( document.createElement( 'ul' ) );

		mountComposable( containerRef, { onReorder: vi.fn() } );

		const optsPassed = lastOptions();
		// sortablejs defaults sort to true; passing `sort: undefined` would clobber that.
		// Same for group — it has internal default-group handling that breaks if you
		// force an `undefined` value into the options object. draggable defaults to
		// '>*'; forcing `undefined` would make nothing draggable.
		expect( 'sort' in optsPassed ).toBe( false );
		expect( 'group' in optsPassed ).toBe( false );
		expect( 'draggable' in optsPassed ).toBe( false );
	} );

	it( 'passes sort and group through when explicitly provided', () => {
		const containerRef = ref<HTMLElement | null>( document.createElement( 'ul' ) );
		const group = { name: 'subjects', pull: true, put: true };

		mountComposable( containerRef, { sort: false, group } );

		const optsPassed = lastOptions();
		expect( optsPassed.sort ).toBe( false );
		expect( optsPassed.group ).toEqual( group );
	} );

	it( 'passes draggable through when explicitly provided', () => {
		const containerRef = ref<HTMLElement | null>( document.createElement( 'ul' ) );

		mountComposable( containerRef, { draggable: '.item:not(.item--hidden)' } );

		expect( lastOptions().draggable ).toBe( '.item:not(.item--hidden)' );
	} );

	it( 'creates a SortableJS instance when the container ref becomes non-null after mount', async () => {
		// Mirrors what happens when the sortable container is rendered behind
		// a v-if that flips after an async load.
		const containerRef = ref<HTMLElement | null>( null );
		mountComposable( containerRef, { onReorder: vi.fn() } );
		expect( mockCreate ).not.toHaveBeenCalled();

		const container = document.createElement( 'ul' );
		containerRef.value = container;
		await flushPromises();

		expect( mockCreate ).toHaveBeenCalledWith( container, expect.anything() );
	} );

	it( 'reverts DOM and calls onReorder on within-container drag, in that order', () => {
		const container = document.createElement( 'ul' );
		const child0 = document.createElement( 'li' );
		const child1 = document.createElement( 'li' );
		const child2 = document.createElement( 'li' );
		container.append( child0, child1, child2 );
		const containerRef = ref<HTMLElement | null>( container );
		let domAtEmitTime: Element[] = [];
		const onReorder = vi.fn( () => {
			domAtEmitTime = Array.from( container.children );
		} );

		mountComposable( containerRef, { onReorder } );

		// SortableJS has moved child0 to the end before onEnd fires.
		container.removeChild( child0 );
		container.appendChild( child0 );

		lastOptions().onEnd( {
			item: child0,
			from: container,
			to: container,
			oldIndex: 0,
			newIndex: 2,
		} );

		expect( onReorder ).toHaveBeenCalledWith( 0, 2 );
		// The revert must run before onReorder fires so the consumer's reactive
		// update lands on a clean DOM.
		expect( domAtEmitTime ).toEqual( [ child0, child1, child2 ] );
	} );

	it( 'does not call onReorder when old and new indices are the same', () => {
		const container = document.createElement( 'ul' );
		const child0 = document.createElement( 'li' );
		container.appendChild( child0 );
		const containerRef = ref<HTMLElement | null>( container );
		const onReorder = vi.fn();

		mountComposable( containerRef, { onReorder } );

		lastOptions().onEnd( {
			item: child0,
			from: container,
			to: container,
			oldIndex: 0,
			newIndex: 0,
		} );

		expect( onReorder ).not.toHaveBeenCalled();
	} );

	it( 'does not call onReorder on cross-container drag (onAdd handles that on the destination)', () => {
		const source = document.createElement( 'ul' );
		const destination = document.createElement( 'ul' );
		const item = document.createElement( 'li' );
		source.appendChild( item );
		const containerRef = ref<HTMLElement | null>( source );
		const onReorder = vi.fn();

		mountComposable( containerRef, { onReorder } );

		// SortableJS has moved the item to the destination before onEnd fires.
		source.removeChild( item );
		destination.appendChild( item );

		lastOptions().onEnd( {
			item,
			from: source,
			to: destination,
			oldIndex: 0,
			newIndex: 0,
		} );

		expect( onReorder ).not.toHaveBeenCalled();
	} );

	it( 'reverts DOM in onEnd for cross-container drag (item returns to source)', () => {
		const source = document.createElement( 'ul' );
		const destination = document.createElement( 'ul' );
		const item = document.createElement( 'li' );
		source.appendChild( item );
		const containerRef = ref<HTMLElement | null>( source );

		mountComposable( containerRef, {} );

		// SortableJS has moved the item to the destination before onEnd fires on source.
		source.removeChild( item );
		destination.appendChild( item );

		lastOptions().onEnd( {
			item,
			from: source,
			to: destination,
			oldIndex: 0,
			newIndex: 0,
		} );

		expect( Array.from( source.children ) ).toEqual( [ item ] );
		expect( Array.from( destination.children ) ).toEqual( [] );
	} );

	it( 'does not require onReorder or onDropIn to be present', () => {
		const container = document.createElement( 'ul' );
		const item = document.createElement( 'li' );
		container.appendChild( item );
		const containerRef = ref<HTMLElement | null>( container );

		expect( () => {
			mountComposable( containerRef, {} );
			lastOptions().onEnd( {
				item,
				from: container,
				to: container,
				oldIndex: 0,
				newIndex: 0,
			} );
			lastOptions().onAdd( { item: document.createElement( 'li' ) } );
		} ).not.toThrow();
	} );

} );
