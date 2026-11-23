<!-- A row of an editor's list: the Schema editor's properties, the Subject editor's open Subjects. Each list
	keeps its own list behaviour; the row is what they look like and how they show a status. -->
<template>
	<li
		role="option"
		class="ext-neowiki-editor-list-item"
		:class="{ 'ext-neowiki-editor-list-item--selected': props.selected }"
		:aria-selected="props.selected"
	>
		<span
			v-if="slots.icon"
			class="ext-neowiki-editor-list-item__icon"
		>
			<slot name="icon" />
			<StatusMark
				v-if="props.status"
				:status="props.status"
			/>
		</span>
		<StatusMark
			v-else-if="props.status"
			:status="props.status"
		/>
		<span class="ext-neowiki-editor-list-item__text">
			<span class="ext-neowiki-editor-list-item__label">
				<slot />
			</span>
			<slot name="description" />
		</span>
		<span
			v-if="props.status"
			class="ext-neowiki-editor-list-item__status"
		>
			{{ statusText( props.status ) }}
		</span>
		<span
			v-if="slots.actions"
			class="ext-neowiki-editor-list-item__actions"
		>
			<slot name="actions" />
		</span>
	</li>
</template>

<script setup lang="ts">
import StatusMark from '@/components/common/StatusMark.vue';
import { statusText, type ItemStatus } from '@/components/common/ItemStatus.ts';

const props = withDefaults( defineProps<{
	selected: boolean;
	status?: ItemStatus | null;
}>(), {
	status: null
} );

const slots = defineSlots<{
	default(): unknown;
	/** Leads the row; the status mark then sits on its corner rather than in the gutter. */
	icon?(): unknown;
	/** A second line under the label. */
	description?(): unknown;
	/** Shown at the row's end on hover, selection and keyboard focus. */
	actions?(): unknown;
}>();
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

/* Codex's menu item states, which CdxMenuItem itself cannot give these rows: it keeps the focus off a row it is
	clicked on, and its padding leaves no room for the gutter. The background is a custom property because the
	actions' fade and the ring around a mark on the icon take it up too, so each state's is opaque. */
.ext-neowiki-editor-list-item {
	--ext-neowiki-editor-list-item-background: @background-color-base;
	position: relative;
	overflow: hidden;
	display: flex;
	align-items: center;
	gap: @spacing-50;
	/* The row reaches the dialog's edge, so the space before its content is the dialog's own gutter. A mark
		hangs there when the row has no icon to put it on. */
	padding: @spacing-50 @spacing-75 @spacing-50 @spacing-150;
	border-start-end-radius: @border-radius-base;
	border-end-end-radius: @border-radius-base;
	background-color: var( --ext-neowiki-editor-list-item-background );
	cursor: pointer;
	list-style: none;
	transition-property: @transition-property-base;
	transition-duration: @transition-duration-base;

	&:hover {
		--ext-neowiki-editor-list-item-background: @background-color-interactive-subtle;
	}

	&:active {
		--ext-neowiki-editor-list-item-background: @background-color-interactive;
		color: @color-emphasized;
	}

	/* Its background stays under the pointer, as Codex's does; only its colour follows the pointer. No change of
		weight: one would re-measure the row under the pointer that selected it. */
	&--selected {
		color: @color-progressive;

		&,
		&:hover,
		&:active {
			--ext-neowiki-editor-list-item-background: @background-color-progressive-subtle;
		}

		&:hover {
			color: @color-progressive--hover;
		}

		&:active {
			color: @color-progressive--active;
		}
	}

	/* A row's open menu reaches past it, over the rows below. */
	&:has( [ aria-expanded='true' ] ) {
		overflow: visible;
	}

	/* Inside the row: the scroller around the list clips at the dialog's edge. */
	&:focus-visible {
		outline: @border-width-thick solid @outline-color-progressive--focus;
		outline-offset: -@border-width-thick;
	}

	/* On the label's first line, however many it wraps to. */
	> .ext-neowiki-status-mark {
		position: absolute;
		inset-block-start: calc( @spacing-50 + ( 1lh - @size-50 ) / 2 );
		inset-inline-start: @spacing-50;
	}

	> .ext-neowiki-status-mark--error {
		inset-block-start: calc( @spacing-50 + ( 1lh - @size-icon-x-small ) / 2 );
		inset-inline-start: @spacing-35;
	}

	&__icon {
		position: relative;
		display: flex;
		flex: none;

		/* A badge on the icon's top-start corner, a step bigger than in the gutter so that the error icon's
			exclamation mark stays legible, and ringed in the row's background to stand apart from the icon. */
		.ext-neowiki-status-mark {
			position: absolute;
			inset-block-start: calc( -1 * ( @size-50 + @spacing-12 ) / 2 );
			inset-inline-start: calc( -1 * ( @size-50 + @spacing-12 ) / 2 );
			width: calc( @size-50 + @spacing-12 );
			height: calc( @size-50 + @spacing-12 );
			box-shadow: 0 0 0 @spacing-12 var( --ext-neowiki-editor-list-item-background );
		}

		.ext-neowiki-status-mark--error {
			inset-block-start: calc( -1 * ( @size-icon-x-small + @spacing-12 ) / 2 );
			inset-inline-start: calc( -1 * ( @size-icon-x-small + @spacing-12 ) / 2 );
			min-width: calc( @size-icon-x-small + @spacing-12 );
			min-height: calc( @size-icon-x-small + @spacing-12 );
			width: calc( @size-icon-x-small + @spacing-12 );
			height: calc( @size-icon-x-small + @spacing-12 );
			border-radius: @border-radius-circle;
			background-color: var( --ext-neowiki-editor-list-item-background );
		}
	}

	&__text {
		flex-grow: 1;
		min-width: 0;
		display: flex;
		flex-direction: column;
		align-items: flex-start;
	}

	/* A long label wraps rather than giving way at its end: the rows are few, so there is room.
		Past two lines it does give way. */
	&__label {
		display: -webkit-box;
		-webkit-box-orient: vertical;
		-webkit-line-clamp: 2;
		overflow: hidden;
		overflow-wrap: anywhere;
	}

	&__status {
		.ext-neowiki-visually-hidden();
	}

	&__actions {
		position: absolute;
		inset-inline-end: 0;
		top: 0;
		bottom: 0;
		display: flex;
		align-items: center;
		gap: @spacing-25;
		padding-inline: @spacing-100 @spacing-50;
		background: linear-gradient( to right, transparent, var( --ext-neowiki-editor-list-item-background ) 40% );
		opacity: 0;
		transform: translateX( @spacing-75 );
		transition: opacity @transition-duration-medium @transition-timing-function-system, transform @transition-duration-medium @transition-timing-function-system;

		// :has( :focus-visible ) rather than :focus-within: keyboard tabbing must reveal the actions, but a mouse
		// click also focuses the button it hits and would keep them pinned after the pointer leaves.
		.ext-neowiki-editor-list-item:hover &,
		.ext-neowiki-editor-list-item--selected &,
		.ext-neowiki-editor-list-item:has( [ aria-expanded='true' ] ) &,
		.ext-neowiki-editor-list-item:has( :focus-visible ) & {
			opacity: 1;
			// None rather than translateX( 0 ): any transform makes a stacking context, which would keep an open menu
			// in it under the rows below.
			transform: none;
		}
	}
}
</style>
