import { describe, it, expect, beforeEach, afterEach, vi } from 'vitest';
import { computed, defineComponent, nextTick, ref } from 'vue';
import { mount } from '@vue/test-utils';
import { paneMaxSize, usePaneSize } from '@/composables/usePaneSize';

// jsdom resolves no layout, so the real observer never reports a width and every
// bound that depends on the container would go unexercised.
const observedWidth = ref<number | undefined>( undefined );

vi.mock( '@wikimedia/codex', async ( importOriginal ) => ( {
	...( await importOriginal<typeof import( '@wikimedia/codex' )>() ),
	useResizeObserver: () => computed( () => ( { width: observedWidth.value, height: 0 } ) ),
} ) );

const KEY = 'neowiki-test-pane-size';

const OPTIONS = {
	defaultSize: 320,
	minSize: 192,
	minOtherSize: 288,
	dividerSize: 0,
	storageKey: KEY,
};

// The composable needs an owning scope: its resize observer is bound to one.
function withPane(
	options: Parameters<typeof usePaneSize>[ 1 ] = OPTIONS,
): ReturnType<typeof usePaneSize> {
	let api!: ReturnType<typeof usePaneSize>;

	mount( defineComponent( {
		template: '<div />',
		setup() {
			api = usePaneSize( ref( null ), options );
			return {};
		},
	} ) );

	return api;
}

function stubStorage( stored: string | null ): { get: ReturnType<typeof vi.fn>; set: ReturnType<typeof vi.fn> } {
	const storage = { get: vi.fn( () => stored ), set: vi.fn( () => true ) };
	( globalThis as unknown as { mw: unknown } ).mw = { storage };
	return storage;
}

describe( 'usePaneSize', () => {

	beforeEach( () => {
		observedWidth.value = undefined;
		delete ( globalThis as unknown as { mw?: unknown } ).mw;
	} );

	afterEach( () => {
		delete ( globalThis as unknown as { mw?: unknown } ).mw;
	} );

	describe( 'without any stored size', () => {

		it( 'starts at the default size', () => {
			expect( withPane().size.value ).toBe( 320 );
		} );

		it( 'reports the minimum as the lower bound', () => {
			expect( withPane().minSize.value ).toBe( 192 );
		} );
	} );

	describe( 'resizing', () => {

		it( 'takes the requested size', () => {
			const pane = withPane();

			pane.resizeTo( 400 );

			expect( pane.size.value ).toBe( 400 );
		} );

		it( 'refuses to go below the minimum', () => {
			const pane = withPane();

			pane.resizeTo( 10 );

			expect( pane.size.value ).toBe( 192 );
		} );

		it( 'rounds a fractional request to a whole pixel', () => {
			const pane = withPane();

			pane.resizeTo( 400.6 );

			expect( pane.size.value ).toBe( 401 );
		} );

		it( 'ignores a request that is not a finite number', () => {
			const pane = withPane();

			pane.resizeTo( Number.NaN );

			expect( pane.size.value ).toBe( 320 );
		} );
	} );

	describe( 'when the container has never been measured', () => {

		it( 'applies no upper bound, so a wide request survives', () => {
			const pane = withPane();

			pane.resizeTo( 5000 );

			expect( pane.size.value ).toBe( 5000 );
		} );

		it( 'reports the current size as the upper bound rather than inventing one', () => {
			const pane = withPane();

			expect( pane.maxSize.value ).toBe( 320 );
		} );

		it( 'keeps the announced range valid', () => {
			const pane = withPane();

			expect( pane.minSize.value ).toBeLessThanOrEqual( pane.maxSize.value );
			expect( pane.size.value ).toBeGreaterThanOrEqual( pane.minSize.value );
			expect( pane.size.value ).toBeLessThanOrEqual( pane.maxSize.value );
		} );
	} );

	describe( 'storage', () => {

		it( 'starts at a stored size', () => {
			stubStorage( '500' );

			expect( withPane().size.value ).toBe( 500 );
		} );

		it( 'falls back to the default when the stored value is not a usable number', () => {
			stubStorage( 'not a number' );

			expect( withPane().size.value ).toBe( 320 );
		} );

		it( 'falls back to the default when storage answers false', () => {
			const storage = { get: vi.fn( () => false ), set: vi.fn( () => true ) };
			( globalThis as unknown as { mw: unknown } ).mw = { storage };

			expect( withPane().size.value ).toBe( 320 );
		} );

		it( 'writes nothing until the size is committed', () => {
			const storage = stubStorage( null );
			const pane = withPane();

			pane.resizeTo( 400 );

			expect( storage.set ).not.toHaveBeenCalled();
		} );

		it( 'writes the committed size', () => {
			const storage = stubStorage( null );
			const pane = withPane();

			pane.resizeTo( 400 );
			pane.persist();

			expect( storage.set ).toHaveBeenCalledWith( KEY, '400' );
		} );

		it( 'writes the size each commit left behind', () => {
			const storage = stubStorage( null );
			const pane = withPane();

			pane.resizeTo( 340 );
			pane.persist();
			pane.resizeTo( 400 );
			pane.persist();

			expect( storage.set ).toHaveBeenCalledTimes( 2 );
			expect( storage.set ).toHaveBeenLastCalledWith( KEY, '400' );
		} );
	} );

	describe( 'without MediaWiki storage available', () => {

		it( 'starts at the default when mw is absent entirely', () => {
			expect( withPane().size.value ).toBe( 320 );
		} );

		it( 'starts at the default when mw.storage lacks get and set', () => {
			( globalThis as unknown as { mw: unknown } ).mw = { storage: { session: {} } };

			expect( withPane().size.value ).toBe( 320 );
		} );

		it( 'commits without throwing', () => {
			( globalThis as unknown as { mw: unknown } ).mw = { storage: { session: {} } };
			const pane = withPane();

			pane.resizeTo( 400 );

			expect( () => pane.persist() ).not.toThrow();
		} );
	} );

	describe( 'the CSS length', () => {

		it( 'carries a unit, because a unitless value voids the whole track list', async () => {
			const pane = withPane();

			pane.resizeTo( 400 );
			await nextTick();

			expect( pane.cssSize.value ).toBe( '400px' );
		} );
	} );

	describe( 'with a measured container', () => {

		it( 'leaves the pane beside it its minimum', () => {
			const pane = withPane();
			observedWidth.value = 900;

			pane.resizeTo( 5000 );

			expect( pane.size.value ).toBe( 612 );
		} );

		it( 'keeps a request the container cannot hold out of the remembered size', () => {
			const storage = stubStorage( null );
			const pane = withPane();
			observedWidth.value = 900;

			pane.resizeTo( 5000 );
			pane.persist();

			expect( storage.set ).toHaveBeenCalledWith( KEY, '612' );
		} );

		// Across ticks on purpose: a watcher that wrote the squeezed width back into the
		// request would not have run yet within one, and that is the bug this pins.
		it( 'gives the pane back its chosen width when the room returns', async () => {
			const pane = withPane();
			observedWidth.value = 1200;
			pane.resizeTo( 700 );

			observedWidth.value = 600;
			await nextTick();
			expect( pane.size.value ).toBe( 312 );

			observedWidth.value = 1200;
			await nextTick();
			expect( pane.size.value ).toBe( 700 );
		} );

		it( 'remembers the chosen width, not the width a squeeze left', async () => {
			const storage = stubStorage( null );
			const pane = withPane();
			observedWidth.value = 1200;
			pane.resizeTo( 700 );

			observedWidth.value = 600;
			await nextTick();
			pane.persist();

			expect( storage.set ).toHaveBeenCalledWith( KEY, '700' );
		} );

		it( 'counts the divider\'s own track against the space the panes share', () => {
			const pane = withPane( { ...OPTIONS, dividerSize: 12 } );
			observedWidth.value = 900;

			pane.resizeTo( 5000 );

			expect( pane.size.value ).toBe( 600 );
		} );

		// A width straight from a ResizeObserver is routinely fractional: browser zoom, or
		// a dialog sized as a percentage. A rounded size against an unrounded bound is how
		// the size ends up just above the maximum being announced.
		it( 'never grows past the maximum it announces, at a fractional width', () => {
			const pane = withPane();

			for ( const width of [ 900.5, 480.4, 613.7, 1000.9, 481.2 ] ) {
				observedWidth.value = width;
				pane.resizeTo( 5000 );

				expect( pane.size.value ).toBeLessThanOrEqual( pane.maxSize.value );
				expect( pane.size.value ).toBeGreaterThanOrEqual( pane.minSize.value );
			}
		} );

		it( 'reports the divider as unmovable where the two minimums no longer fit', () => {
			const pane = withPane();
			observedWidth.value = 400;

			expect( pane.resizable.value ).toBe( false );
			expect( pane.minSize.value ).toBe( pane.maxSize.value );
		} );
	} );
} );

describe( 'paneMaxSize', () => {

	const BOUNDS = { minSize: 192, minOtherSize: 288 };

	it( 'leaves the other pane its minimum', () => {
		expect( paneMaxSize( 900, 1, BOUNDS ) ).toBe( 611 );
	} );

	it( 'never comes out below the pane\'s own minimum', () => {
		expect( paneMaxSize( 400, 1, BOUNDS ) ).toBe( 192 );
	} );

	it( 'stays at the minimum where the two minimums no longer fit', () => {
		// 192 + 1 + 288 = 481: the width at which the interval closes.
		expect( paneMaxSize( 481, 1, BOUNDS ) ).toBe( 192 );
		expect( paneMaxSize( 480, 1, BOUNDS ) ).toBe( 192 );
		expect( paneMaxSize( 300, 1, BOUNDS ) ).toBe( 192 );
	} );

	it( 'never reports a range the wrong way round, at any container width', () => {
		for ( let width = 0; width <= 1600; width += 1 ) {
			expect( paneMaxSize( width, 1, BOUNDS ) ).toBeGreaterThanOrEqual( BOUNDS.minSize );
		}
	} );

	it( 'reports a whole number, so a rounded size cannot land above it', () => {
		for ( const width of [ 900.5, 613.7, 480.4, 1000.9 ] ) {
			expect( Number.isInteger( paneMaxSize( width, 12, BOUNDS ) ) ).toBe( true );
		}
	} );
} );
