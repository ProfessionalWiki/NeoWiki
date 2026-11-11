<template>
	<div
		ref="root"
		class="ext-neowiki-dialog-header"
	>
		<div
			v-if="$slots[ 'start-actions' ]"
			class="ext-neowiki-dialog-header__actions"
		>
			<slot name="start-actions" />
		</div>

		<div class="cdx-dialog__header__title-group">
			<div class="ext-neowiki-dialog-header__title-line">
				<h2 class="cdx-dialog__header__title">
					{{ title }}
				</h2>
				<slot name="metadata" />
			</div>

			<div
				v-if="$slots[ 'supporting-text' ]"
				ref="supportingText"
				class="ext-neowiki-dialog-header__supporting-text"
				:class="{ 'ext-neowiki-dialog-header__supporting-text--collapsed': collapsed }"
				:inert="collapsed || undefined"
				@focusin="onSupportingTextFocusIn"
				@focusout="onSupportingTextFocusOut"
			>
				<div class="ext-neowiki-dialog-header__supporting-text-clip">
					<div class="cdx-dialog__header__subtitle">
						<slot name="supporting-text" />
					</div>
				</div>
			</div>
		</div>

		<div
			v-if="$slots[ 'end-actions' ]"
			class="ext-neowiki-dialog-header__actions"
		>
			<slot name="end-actions" />
		</div>

		<CdxButton
			class="ext-neowiki-dialog-header__close"
			:class="{ 'ext-neowiki-dialog-header__close--phones-only': !useCloseButton }"
			weight="quiet"
			type="button"
			:aria-label="$i18n( 'cdx-dialog-close-button-label' ).text()"
			@click="emit( 'close' )"
		>
			<CdxIcon :icon="cdxIconClose" />
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconClose } from '@wikimedia/codex-icons';

/** How far a scroller may be from its top with the supporting text still shown. */
const SHOWN_WITHIN = 8;
/** What collapsing the supporting text must leave to scroll, or the scroller could end up back within `SHOWN_WITHIN`. */
const ROOM_LEFT = 16;

/**
 * The header of every NeoWiki dialog, for CdxDialog's `#header` slot. CdxDialog then names the dialog by its `title`
 * prop, so the dialog's title is passed to both.
 */
const props = defineProps<{
	title: string;
	/** As Codex 2's: without it, Close shows only on phones, where the dialog may cover the screen. */
	useCloseButton?: boolean;
	/**
	 * Collapses the supporting text while the dialog's body, or the pane of an editor in it scrolled last, is
	 * scrolled down, as long as that leaves something to scroll. It stays while it holds the focus.
	 */
	collapsesSupportingText?: boolean;
}>();

const emit = defineEmits<{
	close: [];
}>();

const root = ref<HTMLElement | null>( null );
const supportingText = ref<HTMLElement | null>( null );
let holdsFocus = false;
// Of the scroller scrolled last: the pane being read, so scrolling either pane back to its top brings the
// supporting text back, whatever the other pane shows.
const scrolledDown = ref( false );

const collapsed = computed( (): boolean => props.collapsesSupportingText === true && scrolledDown.value );

// It stays while it holds the focus, and does not collapse as the focus leaves it either, which would move a pane's
// content under the press that took the focus there: the next scroll decides.
function onSupportingTextFocusIn(): void {
	holdsFocus = true;
	scrolledDown.value = false;
}

function onSupportingTextFocusOut( event: FocusEvent ): void {
	const next = event.relatedTarget;
	holdsFocus = next instanceof Node && ( event.currentTarget as Element ).contains( next );
}

// Not every scroller in the dialog: a menu's list or a text area scrolls too, and neither moves the content.
function isContentScroller( element: Element ): boolean {
	return element.matches( '.cdx-dialog__body, .ext-neowiki-editor-panes__pane' );
}

let lastScroller: HTMLElement | null = null;

function onScroll( event: Event ): void {
	const scroller = event.target;

	if ( props.collapsesSupportingText !== true || !( scroller instanceof HTMLElement ) || !isContentScroller( scroller ) ) {
		return;
	}

	lastScroller = scroller;
	follow( scroller );
}

// A resize can stop a pane scrolling, as where the panes stack below desktop width, which no scroll event reports.
function onResize(): void {
	if ( lastScroller !== null ) {
		follow( lastScroller );
	}
}

function follow( scroller: HTMLElement ): void {
	if ( holdsFocus ) {
		return;
	}

	if ( scroller.scrollTop <= SHOWN_WITHIN ) {
		scrolledDown.value = false;
	} else if ( !scrolledDown.value && leavesRoomCollapsed( scroller ) ) {
		scrolledDown.value = true;
	}
}

// The row's height rather than the text's, which is padded out past the row: the row is what collapsing frees.
function leavesRoomCollapsed( scroller: HTMLElement ): boolean {
	const collapsing = supportingText.value?.offsetHeight ?? 0;
	return scroller.scrollHeight - scroller.clientHeight - collapsing > ROOM_LEFT;
}

// Scrolling does not bubble, but it is captured on the way down to the scroller.
let dialog: Element | null = null;

onMounted( () => {
	dialog = root.value?.closest( '.cdx-dialog' ) ?? null;
	dialog?.addEventListener( 'scroll', onScroll, { capture: true, passive: true } );
	window.addEventListener( 'resize', onResize, { passive: true } );
} );

onBeforeUnmount( () => {
	dialog?.removeEventListener( 'scroll', onScroll, { capture: true } );
	window.removeEventListener( 'resize', onResize );
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

/* Codex's `--default` header layout, which a slotted header does not get, aligned to the top so that Close stays on
	the title's line as the supporting text wraps. The title's line is as tall as Close, and its letters are trimmed to
	their capitals and baseline, so that they are centred on Close's icon. */
.ext-neowiki-dialog-header {
	display: flex;
	align-items: flex-start;
	column-gap: @spacing-50;
	box-sizing: @box-sizing-base;
	width: @size-full;

	.cdx-dialog__header__title-group {
		min-width: 0;
	}

	&__title-line {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: @spacing-25 @spacing-50;
		min-height: @size-200;

		.cdx-dialog__header__title {
			text-box: trim-both cap alphabetic;
		}
	}

	&__actions {
		display: flex;
	}

	/* Collapsed by its row rather than its height, which cannot be animated to from `auto`. */
	&__supporting-text {
		display: grid;
		grid-template-rows: 1fr;
		transition-property: grid-template-rows, opacity;
		transition-duration: @transition-duration-medium;
		transition-timing-function: @transition-timing-function-user;

		&--collapsed {
			grid-template-rows: 0fr;
			opacity: 0;
		}

		@media ( prefers-reduced-motion: reduce ) {
			transition: none;
		}
	}

	/* Padded out and pulled back by as much, so the clip leaves room for what its controls draw outside themselves,
		such as the focus ring and hover area of EditableText's buttons. */
	&__supporting-text-clip {
		min-height: 0;
		overflow: hidden;
		margin: -@spacing-50;
		padding: @spacing-50;
	}

	/* Codex's own pull into the padding, so the icon rather than the button lines up with the content's edge. Nested,
		to outrank the margin and display Codex gives every button. Not Codex's close button class: Codex 2.3.3 and later
		hide that from 640px up in a dialog without `useCloseButton`, which this header decides for itself. */
	& &__close {
		margin-inline-end: -@spacing-50;
	}

	& &__close--phones-only {
		@media ( min-width: @min-width-breakpoint-tablet ) {
			display: none;
		}
	}
}
</style>
