import { computed, ComputedRef, Ref, ref } from 'vue';
import { useResizeObserver } from '@wikimedia/codex';

export interface PaneSizeOptions {
	/** The pane's width before the reader has chosen one, in CSS pixels. */
	defaultSize: number;
	/** The narrowest the pane may be made. */
	minSize: number;
	/** The narrowest the pane beside it may be squeezed to before this one stops growing. */
	minOtherSize: number;
	/** Where the reader's choice is remembered. Per host: two panes are never the same pane. */
	storageKey: string;
	/** The width of the divider's own track, counted against the space the two panes share. */
	dividerSize: number;
}

/**
 * The widest the pane may be in a container of the given width.
 *
 * Floored against the pane's own minimum before the space left over is measured
 * against it, so the bound can never come out below the floor. Without that, a
 * container too narrow to hold both panes reports an upper bound beneath the lower
 * one, which is an invalid range to announce and reverses which pane gets squeezed.
 * Floored, the pane keeps its minimum and the one beside it gives up the difference.
 */
export function paneMaxSize(
	containerWidth: number,
	dividerSize: number,
	options: Pick<PaneSizeOptions, 'minSize' | 'minOtherSize'>,
): number {
	// Floored, not merely maxed: an observed width is routinely fractional, and a bound
	// left fractional while the size is rounded lets the size land just above it — the
	// out-of-range pair this function exists to rule out.
	return Math.max( options.minSize, Math.floor( containerWidth - dividerSize - options.minOtherSize ) );
}

/**
 * The width of a resizable pane, in CSS pixels.
 *
 * Two quantities, and only the first is state: `requested` is what the reader
 * asked for, written by their gestures alone, and `size` is what fits right
 * now. A container that narrows therefore changes no state — it changes a
 * computed — so the pane returns to the width the reader chose as soon as the
 * room comes back, and what is remembered is their choice rather than the
 * squeeze. Anything that wrote a measured width back into `requested` would
 * quietly destroy that choice.
 */
export function usePaneSize(
	container: Ref<HTMLElement | null>,
	options: PaneSizeOptions,
): {
		size: ComputedRef<number>;
		cssSize: ComputedRef<string>;
		minSize: ComputedRef<number>;
		maxSize: ComputedRef<number>;
		resizable: ComputedRef<boolean>;
		resizeTo: ( size: number ) => void;
		persist: () => void;
	} {
	const requested = ref( readStoredSize( options.storageKey ) ?? options.defaultSize );

	const observed = useResizeObserver( computed( () => container.value ?? undefined ) );

	// One reading covers three cases that must all mean "no upper bound yet": jsdom, which
	// resolves no layout; the frame before the first observation; and a host that is not
	// displayed, which a real observer reports as zero.
	const measured = computed( (): number | null => {
		const width = observed.value?.width;
		return width !== undefined && width > 0 ? width : null;
	} );

	const maxSize = computed( (): number => measured.value === null ?
		Math.max( options.minSize, size.value ) :
		paneMaxSize( measured.value, options.dividerSize, options ) );

	const minSize = computed( (): number => options.minSize );

	const size = computed( (): number => clamp( requested.value ) );

	const cssSize = computed( (): string => `${ size.value }px` );

	// Announced so a reader is told the divider cannot move, rather than finding it inert.
	const resizable = computed( (): boolean => maxSize.value > minSize.value );

	function clamp( value: number ): number {
		const floored = Math.max( value, options.minSize );
		return Math.round( measured.value === null ? floored : Math.min( floored, maxSize.value ) );
	}

	// Clamped as it is stored: a gesture that overshot must not be kept and replayed on a
	// wider screen.
	//
	// Unless the clamp changes nothing on screen, which means the container refused the
	// request rather than the reader making one. Recording that would put the squeeze where
	// the reader's own width was — the width they chose on a wider screen would be replaced
	// by whatever fits here, by a keystroke that moved the divider not one pixel.
	function resizeTo( value: number ): void {
		if ( !Number.isFinite( value ) ) {
			return;
		}

		const clamped = clamp( value );

		if ( clamped === size.value ) {
			return;
		}

		requested.value = clamped;
	}

	// Called once a gesture is over rather than as it moves, so a drag or a held key is
	// one write. Immediate, so nothing is owed at the moment the page goes away.
	function persist(): void {
		writeStoredSize( options.storageKey, requested.value );
	}

	return { size, cssSize, minSize, maxSize, resizable, resizeTo, persist };
}

/**
 * The methods are looked for rather than the object: component tests run without
 * `mw` at all, and the shared test mock supplies a session store that has neither.
 */
function paneStorage(): Pick<typeof mw.storage, 'get' | 'set'> | null {
	const storage = typeof mw === 'undefined' ? undefined : mw.storage;

	return typeof storage?.get === 'function' && typeof storage.set === 'function' ?
		storage :
		null;
}

/**
 * `get` answers null for a key that was never written and false where storage is
 * unavailable; a reader's own tooling can leave anything at all there. One number
 * conversion rejects every one of those.
 */
function readStoredSize( key: string ): number | null {
	const stored = Number( paneStorage()?.get( key ) );

	return Number.isFinite( stored ) && stored > 0 ? stored : null;
}

function writeStoredSize( key: string, size: number ): void {
	paneStorage()?.set( key, String( size ) );
}
