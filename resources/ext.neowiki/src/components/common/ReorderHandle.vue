<template>
	<!-- A click selects nothing in the row around the handle, nor opens a <details> whose summary holds it. -->
	<span
		ref="rootRef"
		class="ext-neowiki-reorder-handle"
		@click.stop.prevent
		@focusout="onFocusout"
	>
		<CdxButton
			:id="buttonId"
			ref="buttonRef"
			weight="quiet"
			:disabled="props.count < 2"
			:aria-label="props.label"
			:title="tooltip"
			aria-haspopup="menu"
			:aria-expanded="expanded"
			:aria-controls="menuId"
			aria-keyshortcuts="Alt+ArrowUp Alt+ArrowDown"
			data-mw-neowiki-reorder-handle=""
			@click="onClick"
			@pointerdown="keyHandledClick = false"
			@keydown="onKeydown"
			@blur="expanded = false"
		>
			<CdxIcon :icon="cdxIconDraggable" />
		</CdxButton>
		<span class="ext-neowiki-reorder-handle__menu">
			<CdxMenu
				:id="menuId"
				ref="menuRef"
				v-model:expanded="expanded"
				:selected="null"
				:menu-items="menuItems"
				role="menu"
				:aria-labelledby="buttonId"
				@update:selected="onSelected"
			/>
		</span>
	</span>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxButton, CdxIcon, CdxMenu, useFloatingMenu, useGeneratedId } from '@wikimedia/codex';
import { cdxIconDraggable } from '@wikimedia/codex-icons';
import { isReorderMove, positionAfter, REORDER_HANDLE_SELECTOR, reorderMenuItems, type ReorderMove } from '@/components/common/reordering.ts';

/**
 * The handle an item of a reorderable list is dragged by. Clicked, it offers moves that need no
 * dragging; Alt+ArrowUp and Alt+ArrowDown move the item one place.
 *
 * A menu button of its own, since CdxMenuButton fumbles Enter before Codex 2.5 (MediaWiki 1.46): it reopens
 * the menu after a move, or closes it without one. Switch back to CdxMenuButton once the oldest MediaWiki
 * NeoWiki supports ships Codex 2.5, keeping the Alt+arrows and the Tab rule below.
 */
const props = defineProps<{
	/** The item's place in the list, from 0. */
	position: number;
	count: number;
	label: string;
}>();

const emit = defineEmits<{
	/** The place the item is to take, from 0. */
	move: [ to: number ];
}>();

const rootRef = ref<HTMLElement | null>( null );
const buttonRef = ref<InstanceType<typeof CdxButton>>();
const menuRef = ref<InstanceType<typeof CdxMenu>>();
const buttonId = useGeneratedId( 'ext-neowiki-reorder-handle' );
const menuId = useGeneratedId( 'ext-neowiki-reorder-menu' );
const expanded = ref( false );

const tooltip = mw.message( 'neowiki-reorder-handle-tooltip' ).text();

// Through the booleans, so that only the handles at the ends rebuild their menus when the list changes.
const atTop = computed( () => props.position === 0 );
const atBottom = computed( () => props.position === props.count - 1 );
const menuItems = computed( () => reorderMenuItems( atTop.value, atBottom.value ) );

// The cast is DataExportButtons.vue's, which explains it.
useFloatingMenu(
	buttonRef as unknown as Parameters<typeof useFloatingMenu>[0],
	menuRef,
	{ useAvailableWidth: true, placement: 'bottom-start', offset: 4 }
);

function onSelected( value: string | number | null ): void {
	if ( isReorderMove( value ) ) {
		moveTo( value );
	}
}

// CdxButton clicks itself on the keyup of an Enter or Space it has kept from the browser. That click belongs
// to a key handled already, so it is spent; a pointer press is never one.
let keyHandledClick = false;

function onClick(): void {
	if ( keyHandledClick ) {
		keyHandledClick = false;
	} else {
		expanded.value = !expanded.value;
	}
}

// Enter and Space make the move highlighted with the keyboard, or else open or close the menu. Tab is kept
// from the menu, which would make the highlighted move; the focus still leaves, which closes the menu.
function onKeydown( event: KeyboardEvent ): void {
	const move = altArrowMove( event );

	if ( move !== null ) {
		event.preventDefault();
		event.stopPropagation();
		moveTo( move );
		return;
	}

	if ( event.key === 'Enter' || event.key === ' ' ) {
		keyHandledClick = true;

		if ( !highlightedWithKeyboard() ) {
			event.preventDefault();
			expanded.value = !expanded.value;
			return;
		}
	}

	if ( event.key !== 'Tab' ) {
		menuRef.value?.delegateKeyNavigation( event );
	}
}

function highlightedWithKeyboard(): boolean {
	return expanded.value &&
		menuRef.value?.getHighlightedMenuItem() !== null &&
		menuRef.value?.getHighlightedViaKeyboard() === true;
}

function altArrowMove( event: KeyboardEvent ): ReorderMove | null {
	if ( !event.altKey || event.ctrlKey || event.metaKey || event.shiftKey ) {
		return null;
	}

	return { ArrowUp: 'move-up' as const, ArrowDown: 'move-down' as const }[ event.key ] ?? null;
}

let refocusOnArrival = false;

function moveTo( move: ReorderMove ): void {
	const to = positionAfter( move, props.position, props.count );

	if ( to !== props.position ) {
		refocusOnArrival = true;
		emit( 'move', to );
	}
}

// The list moving the item's element drops the focus from it. The list may move it later than the
// emit, such as after a save, so the handle takes the focus back once it has arrived, unless the
// user has put the focus somewhere else meanwhile.
watch( () => props.position, () => {
	if ( refocusOnArrival && focusWasDropped() ) {
		rootRef.value?.querySelector<HTMLElement>( REORDER_HANDLE_SELECTOR )?.focus();
	}

	refocusOnArrival = false;
}, { flush: 'post' } );

function focusWasDropped(): boolean {
	return document.activeElement === document.body;
}

// A move that has not arrived by the time the user moves on, such as one whose save failed, is not one to
// take the focus back for. The list moving the element drops the focus to nothing instead.
function onFocusout( event: FocusEvent ): void {
	if ( event.relatedTarget !== null ) {
		refocusOnArrival = false;
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-reorder-handle {
	display: inline-flex;
	flex-shrink: 0;

	/* Grab rather than Codex's pointer: dragging is what the handle is first for, its menu the alternative. */
	[ data-mw-neowiki-reorder-handle ]:enabled:hover {
		cursor: @cursor-grab;
	}

	&__menu {
		position: relative;

		// The width CdxMenuButton gives its menu, which takes the width available to it.
		.cdx-menu {
			min-width: 8rem;
			max-width: 16rem;
		}
	}
}
</style>
