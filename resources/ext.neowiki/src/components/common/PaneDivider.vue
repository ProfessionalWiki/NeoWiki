<template>
	<div
		ref="root"
		class="ext-neowiki-pane-divider"
		role="separator"
		tabindex="0"
		aria-orientation="vertical"
		:aria-label="label"
		:aria-controls="controls"
		:aria-valuenow="size"
		:aria-valuemin="min"
		:aria-valuemax="max"
		:aria-disabled="disabled || undefined"
		@pointerdown="onPointerDown"
		@pointermove="onPointerMove"
		@pointerup="endDrag"
		@pointercancel="endDrag"
		@keydown="onKeydown"
		@keyup="onKeyup"
		@blur="onKeyup"
	/>
</template>

<script lang="ts">
/**
 * The width of the track the divider occupies. Callers count it against the space the
 * two panes share, so it has to be a number they can reach as well as a length in the
 * styles below, where it is `@spacing-75`.
 */
export const PANE_DIVIDER_SIZE = 12;
</script>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';

const RESIZING_CLASS = 'ext-neowiki-pane-resizing';

/** One @spacing-100 of the layout's rhythm. */
const SMALL_STEP = 16;
/** Eight of them, so a full travel takes a few presses rather than a few dozen. */
const LARGE_STEP = 128;

const props = defineProps<{
	/** The pane's current width in CSS pixels, as the layout is rendering it. */
	size: number;
	min: number;
	max: number;
	/** Names the divider for assistive technology. Callers own their own wording. */
	label: string;
	/** The id of the pane this sizes. */
	controls: string;
	/** True where the two panes' minimums already fill the container. */
	disabled?: boolean;
}>();

const emit = defineEmits<{
	/** A size the gesture is asking for. The host is what bounds it. */
	resize: [ size: number ];
	/** The gesture is over, and the size it left behind is worth remembering. */
	commit: [];
}>();

const root = ref<HTMLElement | null>( null );

// A drag is in progress exactly while this holds a pointer.
let dragPointerId: number | null = null;
let dragStartX = 0;
let dragStartSize = 0;
let dragSign = 1;
let draggedPointer = false;
let movedByKey = false;
let keySign = 1;

/**
 * Which way a growing pane moves the pointer. CSSJanus rewrites the stylesheet and
 * leaves JavaScript alone, so this is the one quantity that has to be mirrored by
 * hand — and only for physical directions, never for Home and End.
 *
 * Read once per gesture rather than per move: reading style mid-drag would force a
 * synchronous recalc against the write the previous move just made.
 */
function directionSign(): number {
	const element = root.value;

	if ( element === null ) {
		return 1;
	}

	return window.getComputedStyle( element ).direction === 'rtl' ? -1 : 1;
}

function onPointerDown( event: PointerEvent ): void {
	if ( props.disabled === true || event.button !== 0 || dragPointerId !== null ) {
		return;
	}

	// Suppresses the compatibility mouse events, and with them the text selection a
	// drag across two panes of prose would otherwise start. Focus goes back by hand,
	// since that is the other thing the default action would have done.
	event.preventDefault();
	root.value?.focus();

	dragPointerId = event.pointerId;
	dragStartX = event.clientX;
	dragStartSize = props.size;
	dragSign = directionSign();
	setResizingMarker( true );

	// Last, and allowed to fail: capture only widens where the moves are delivered, so a
	// pointer the browser no longer counts as active costs us nothing — whereas asking
	// for it before the drag was recorded would throw the whole gesture away.
	try {
		root.value?.setPointerCapture( event.pointerId );
	} catch {
		// The element still receives the moves that land on it.
	}
}

function onPointerMove( event: PointerEvent ): void {
	if ( dragPointerId !== event.pointerId ) {
		return;
	}

	// Without capture the release can land on another element, leaving the drag with no
	// end. A move with nothing held is that state, and ending here rather than following
	// the cursor is what stops the page keeping its resize cursor and its selection lock.
	if ( event.buttons === 0 ) {
		endDrag( event );
		return;
	}

	// Measured from where the drag began rather than from the last move, so a size the
	// host declined to grant does not accumulate an offset the pointer never travelled.
	draggedPointer = true;
	emit( 'resize', dragStartSize + dragSign * ( event.clientX - dragStartX ) );
}

function endDrag( event: PointerEvent ): void {
	if ( dragPointerId !== event.pointerId ) {
		return;
	}

	// Capture is released implicitly on both events that get here, and asking for it
	// again throws once the pointer is gone — which is exactly what pointercancel
	// reports. A throw here would strand the page-wide cursor lock set below.
	dragPointerId = null;
	setResizingMarker( false );

	if ( draggedPointer ) {
		draggedPointer = false;
		emit( 'commit' );
	}
}

// Pointer capture routes the events to this element but leaves the cursor to whatever
// is under it, so the drag cursor and the selection block have to be page-wide.
function setResizingMarker( resizing: boolean ): void {
	document.documentElement.classList.toggle( RESIZING_CLASS, resizing );
}

// A dialog torn down mid-drag never sees its pointerup. Guarded, because the marker is
// page-wide and another divider may be the one dragging: five dialogs render the schema
// editor, and a nested one closing must not drop the cursor of a drag still in progress.
onBeforeUnmount( () => {
	if ( dragPointerId !== null ) {
		setResizingMarker( false );
	}

	// A dialog closed on Escape takes the focused divider with it, so no keyup and no blur
	// ever arrive and the width the reader just set would go unremembered.
	if ( dragPointerId !== null || movedByKey ) {
		emit( 'commit' );
	}
} );

function onKeydown( event: KeyboardEvent ): void {
	// Modified arrows belong to the browser and the platform — Alt+Left is Back — so they
	// are left alone rather than resized with and swallowed.
	if ( event.altKey || event.ctrlKey || event.metaKey || event.shiftKey ) {
		return;
	}

	if ( props.disabled === true || !movesTheDivider( event.key ) ) {
		return;
	}

	// Only for keys this acts on: the surfaces on either side scroll on the rest, and
	// the dialog closes on Escape.
	event.preventDefault();
	event.stopPropagation();

	// Once per gesture, like the pointer path: the repeat rate of a held arrow is fast
	// enough that a style read per step would land after the write the last one made.
	if ( !movedByKey ) {
		keySign = directionSign();
		movedByKey = true;
	}

	emit( 'resize', sizeForKey( event.key, keySign ) );
}

// Key repeat sends many keydowns and exactly one keyup, so this is where a held arrow
// stops rather than where each step lands: one gesture, one commit. Also bound to blur,
// or a gesture interrupted by a click elsewhere would never be committed, and the next
// keyup of any key at all would be committed in its place.
function onKeyup(): void {
	if ( movedByKey ) {
		movedByKey = false;
		emit( 'commit' );
	}
}

function movesTheDivider( key: string ): boolean {
	return [ 'ArrowLeft', 'ArrowRight', 'PageDown', 'PageUp', 'Home', 'End' ].includes( key );
}

// Only the arrows are mirrored: Home and End name ends of the value, not sides of the
// screen, and the paging keys follow them.
function sizeForKey( key: string, sign: number ): number {
	switch ( key ) {
		case 'ArrowLeft':
			return props.size - sign * SMALL_STEP;
		case 'ArrowRight':
			return props.size + sign * SMALL_STEP;
		case 'PageDown':
			return props.size - LARGE_STEP;
		case 'PageUp':
			return props.size + LARGE_STEP;
		case 'Home':
			return props.min;
		default:
			return props.max;
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

/**
 * The rule between two panes, and the target for moving it.
 *
 * The rule is a hairline but the track is not: the element fills a real column of its
 * own and centres the line inside it. An earlier draft made the element wider than a
 * one-pixel track and hung the difference over both neighbours on negative margins,
 * which buys grab area at the cost of covering the trailing edge of the pane before
 * it — and in both hosts that edge is where that pane's own scrollbar sits.
 *
 * Occupying a track rather than overlapping one also means nothing here has to be
 * positioned. That matters beyond tidiness: Codex un-positions the wrappers around
 * menus inside a dialog so they escape the scrolling body, and anything between a
 * field and the dialog that became a containing block would clip every one of them.
 *
 * A 12px target still does not meet WCAG 2.5.8 Target Size (Minimum), which no
 * divider drawn as a hairline can. The keyboard path is the accessible one rather
 * than a courtesy.
 */
.ext-neowiki-pane-divider {
	display: flex;
	justify-content: center;
	inline-size: @spacing-75;
	cursor: col-resize;
	// Or the browser claims the gesture for panning before the first move arrives.
	touch-action: none;
	user-select: none;

	/* A border rather than a painted box: forced-colors mode keeps borders and replaces
		backgrounds with the canvas, which would leave the rule invisible exactly where the
		12px target is hardest to hit. Printing drops backgrounds too. */
	&::before {
		content: '';
		border-inline-start: @border-subtle;
	}

	&:focus-visible {
		outline: @border-width-thick solid @outline-color-progressive--focus;
		outline-offset: -@border-width-thick;
	}

	&[ aria-disabled='true' ] {
		cursor: default;
	}
}

/* While a drag is live the cursor belongs to whatever the pointer is over, and a
	selection begun before it must not extend. Both have to out-rank whatever that
	element sets for itself, which nothing but a weight override reaches. */
.ext-neowiki-pane-resizing,
.ext-neowiki-pane-resizing * {
	cursor: col-resize !important;
	user-select: none !important;
}
</style>
