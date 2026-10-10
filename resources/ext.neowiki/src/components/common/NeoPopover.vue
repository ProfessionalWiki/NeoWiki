<template>
	<div
		ref="rootRef"
		class="ext-neowiki-popover"
		@focusout="closeWhenFocusLeaves"
		@keyup.esc="closeOnEscape"
	>
		<slot
			name="trigger"
			:set-trigger="setTrigger"
			:toggle="toggle"
			:open="props.open"
			:panel-id="panelId"
		/>

		<!-- Shown rather than created, the way a Codex menu is: useFloatingMenu positions an element
			that is already there, and one appearing on open would be painted once where it happens to
			sit before being moved. -->
		<div
			v-show="props.open"
			:id="panelId"
			ref="panelRef"
			class="ext-neowiki-popover__panel"
			@mousedown="keepFocusInside"
		>
			<slot />
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, ref, watch } from 'vue';
import type { ComponentPublicInstance } from 'vue';
import { useFloatingMenu, useGeneratedId } from '@wikimedia/codex';
import type { FloatingMenuOptions } from '@wikimedia/codex';

// How many frames to keep trying to put the focus in the panel while it is being placed.
const FOCUS_ATTEMPTS = 10;

interface NeoPopoverProps {
	open: boolean;
	placement?: FloatingMenuOptions[ 'placement' ];
	offset?: number;
}

const props = withDefaults(
	defineProps<NeoPopoverProps>(),
	{
		placement: 'bottom-end',
		offset: 4
	}
);

const emit = defineEmits<{
	'update:open': [ open: boolean ];
}>();

const panelId = useGeneratedId( 'ext-neowiki-popover-panel' );

const rootRef = ref<HTMLElement | null>( null );
const panelRef = ref<HTMLElement | null>( null );

/**
 * The trigger goes in as the component rather than as its element: handed an element, Codex passes
 * the ref object itself on to floating-ui's autoUpdate, which resolves it to nothing, so nothing
 * watches the trigger and the panel never follows it. Set from the slot, since the trigger is the
 * host's to render.
 */
const triggerRef = ref<HTMLElement | { $el?: HTMLElement } | null>( null );

function setTrigger( instance: Element | ComponentPublicInstance | null ): void {
	triggerRef.value = instance as HTMLElement | { $el?: HTMLElement } | null;
}

const triggerElement = computed( (): HTMLElement | undefined => {
	const instance = triggerRef.value;

	return instance instanceof HTMLElement ? instance : instance?.$el;
} );

// All useFloatingMenu reads of the element it places: whether it is showing, and its root node. It
// asks by type for a CdxMenu, the component it was written against.
const floatingPanel = computed( () => panelRef.value === null ?
	undefined :
	{ $el: panelRef.value, isExpanded: (): boolean => props.open }
);

/**
 * Flipping to the other side when there is no room, clamping to the space left, and hiding when
 * the trigger scrolls out of view all come with this.
 */
useFloatingMenu(
	triggerRef as unknown as Parameters<typeof useFloatingMenu>[ 0 ],
	floatingPanel as unknown as Parameters<typeof useFloatingMenu>[ 1 ],
	{ placement: props.placement, offset: props.offset }
);

function toggle(): void {
	if ( props.open ) {
		close();
		return;
	}

	emit( 'update:open', true );
}

function close(): void {
	emit( 'update:open', false );
}

/**
 * useFloatingMenu shows the panel only once it has placed it, a frame or more after the panel
 * opens, and a field in a hidden panel cannot take the focus. So the focus is tried each frame
 * until it takes, which on a first open below the fold is not the first try. The scroll it would
 * make is left out: that would read as the page scrolling, which closes the panel.
 */
async function focusPanel(): Promise<void> {
	const field = panelRef.value?.querySelector( 'input, textarea, select' );

	if ( !( field instanceof HTMLElement ) ) {
		return;
	}

	for ( let attempt = 0; attempt < FOCUS_ATTEMPTS && props.open; attempt++ ) {
		field.focus( { preventScroll: true } );

		if ( document.activeElement === field ) {
			return;
		}

		await nextFrame();
	}
}

function nextFrame(): Promise<void> {
	return new Promise( ( resolve ) => {
		requestAnimationFrame( () => resolve() );
	} );
}

/**
 * Escape closes the panel wherever the focus is in it, and goes no further: Codex dialogs close on
 * the Escape keyup, which would take everything typed into the dialog with it. With the panel
 * closed, Escape is the dialog's as usual. The Escape that cancels an IME composition is not a
 * cancel.
 */
function closeOnEscape( event: KeyboardEvent ): void {
	if ( !props.open || event.isComposing ) {
		return;
	}

	event.stopPropagation();
	close();
}

/**
 * Focus leaving the popover for somewhere else on the page is the user done with it. Focus going
 * nowhere is left alone: that is the window losing focus, and the panel waits for the user's
 * return, while a press elsewhere on the page closes it from onDocumentMousedown.
 */
function closeWhenFocusLeaves( event: FocusEvent ): void {
	if ( event.relatedTarget instanceof Node && !rootRef.value?.contains( event.relatedTarget ) ) {
		close();
	}
}

/**
 * A press anywhere in the panel but a field of its own, such as on a search icon or on a menu's
 * scrollbar in Firefox, would move the focus out of the panel, and so close it. Codex prevents
 * that on its menu items only.
 */
function keepFocusInside( event: MouseEvent ): void {
	if ( !( event.target instanceof HTMLInputElement ) ) {
		event.preventDefault();
	}
}

// mousedown rather than click: a pointer that goes down outside and up inside must not read as
// having stayed inside.
function onDocumentMousedown( event: MouseEvent ): void {
	if ( !rootRef.value?.contains( event.target as Node ) ) {
		close();
	}
}

/**
 * Scrolling what the popover sits in moves the trigger away from under its panel, and once it
 * leaves the visible area useFloatingMenu hides the panel, which drops the focus in it to the
 * page. So the panel closes instead, handing the focus back to the trigger, which scrolling does
 * not take it from. Only something the popover sits inside counts as that: the panel's own list
 * scrolling is the user browsing it, and a text field left behind scrolls its text back to the
 * start, which is not the page moving at all.
 */
function closeOnScroll( event: Event ): void {
	if ( event.target instanceof Node && event.target.contains( rootRef.value ) ) {
		close();
	}
}

// Listened for only while the panel is open: a page may hold many popovers, and a closed one has
// nothing to close.
watch( () => props.open, async ( isOpen ) => {
	if ( isOpen ) {
		document.addEventListener( 'mousedown', onDocumentMousedown );
		// Captured, since scroll events do not bubble.
		document.addEventListener( 'scroll', closeOnScroll, true );

		await nextTick();
		await focusPanel();
		return;
	}

	stopListeningToThePage();

	// Whoever closed it, the user was in it, so the focus comes back to what opened it rather than
	// being hidden away with the panel and dropped on the document. A host that closes the panel
	// itself - once a choice made in it has been taken - needs nothing of its own for this. Focus
	// already gone elsewhere was the user leaving, and is left where they put it.
	returnFocusIfItIsStillInside();
}, { immediate: true } );

function returnFocusIfItIsStillInside(): void {
	if ( rootRef.value?.contains( document.activeElement ) === true ) {
		triggerElement.value?.focus( { preventScroll: true } );
	}
}

onBeforeUnmount( stopListeningToThePage );

function stopListeningToThePage(): void {
	document.removeEventListener( 'mousedown', onDocumentMousedown );
	document.removeEventListener( 'scroll', closeOnScroll, true );
}

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-popover {
	/* Frameless: whatever the host puts in the panel draws its own borders, and a second one around
		all of it reads as a box inside a box. Its position, width, max-height and visibility are
		written onto it by useFloatingMenu, which sizes it to the trigger; the floor is ours, since a
		trigger is often narrower than the panel it opens. */
	&__panel {
		position: absolute;
		z-index: @z-index-dropdown;
		box-sizing: @box-sizing-base;
		min-width: @size-1600;
		box-shadow: @box-shadow-drop-medium;
		display: flex;
		flex-direction: column;
		background-color: @background-color-base;
		/* useFloatingMenu clamps the panel's height to the room left in the viewport. What is inside
			is what gives way, scrolling within what is left. */
		overflow: hidden;
	}
}
</style>
