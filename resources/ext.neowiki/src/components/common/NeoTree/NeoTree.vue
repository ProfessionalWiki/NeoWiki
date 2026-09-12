<template>
	<nav
		class="ext-neowiki-tree"
		:aria-label="label"
	>
		<!-- Labelled again here: a widget role takes no name from the landmark around it. -->
		<ul
			class="ext-neowiki-tree__list"
			role="tree"
			:aria-label="label"
		>
			<NeoTreeNode
				v-for="item in items"
				:key="item.key"
				:item="item"
				:element-ids="elementIds"
				:roving-key="rovingKey"
				:select="selectItem"
				:toggle="toggleItem"
				:keydown="onKeydown"
			>
				<template #trailing="slotProps">
					<slot
						name="trailing"
						v-bind="slotProps"
					/>
				</template>
				<template #end="slotProps">
					<slot
						name="end"
						v-bind="slotProps"
					/>
				</template>
			</NeoTreeNode>
		</ul>
	</nav>
</template>

<script setup lang="ts" generic="T">
import { computed, nextTick, ref } from 'vue';
import NeoTreeNode from './NeoTreeNode.vue';
import { isExpanded, isTogglable } from './NeoTreeModel.ts';
import type { NeoTreeItem } from './NeoTreeModel.ts';

const props = defineProps<{
	items: NeoTreeItem<T>[];
	label: string;
}>();

const emit = defineEmits<{
	/** A row was chosen. Whatever that costs the caller, only this gesture spends it. */
	select: [ NeoTreeItem<T> ];
	/** The disclosure control was pressed. The caller owns the expansion state. */
	toggle: [ NeoTreeItem<T> ];
}>();

// Declared out here: a parenthesis anywhere inside a macro's type argument trips ESLint's
// func-call-spacing, which reads it as the macro's own call.
type NodeSlot = ( slotProps: { item: NeoTreeItem<T> } ) => unknown;

defineSlots<{
	trailing?: NodeSlot;
	end?: NodeSlot;
}>();

// An item as printed, with the key of the item it sits under: Left climbs out of a node, and
// nothing in the item itself says what it hangs from.
interface PrintedItem<U> {
	item: NeoTreeItem<U>;
	parentKey: string | null;
}

// Printed order, which is both the order Up/Down move through and the order the element ids
// are numbered in. A top-level item's `groupLabel` is dropped: the tree renders its items
// without grouping them, so nothing decides whether that caption takes a line or a row.
const printedItems = computed( (): PrintedItem<T>[] => {
	const items: PrintedItem<T>[] = [];

	for ( const item of props.items ) {
		items.push( ...flatten( item, null ) );
	}

	return items;
} );

const flatItems = computed( (): NeoTreeItem<T>[] =>
	printedItems.value.map( ( printed ) => printed.item ) );

// A collapsed item's children are not printed, so they are not in this list either: a key here
// that nothing rendered leaves the roving focus on an element that is not there.
function flatten( item: NeoTreeItem<T>, parentKey: string | null ): PrintedItem<T>[] {
	const items: PrintedItem<T>[] = [ { item, parentKey } ];
	const children = item.children ?? [];

	if ( isExpanded( item ) ) {
		for ( const child of children ) {
			items.push( ...flatten( child, item.key ) );
		}
	}

	return items;
}

const elementIds = computed( (): ReadonlyMap<string, string> =>
	new Map( flatItems.value.map( ( item, index ) => [ item.key, elementId( index ) ] ) ) );

function elementId( index: number ): string {
	return `ext-neowiki-tree-node-${ index }`;
}

// Set by Up/Down/Home/End; null until one is pressed, and again whenever the key it holds
// leaves the tree.
const focusedKey = ref<string | null>( null );

const rovingKey = computed( (): string | null => {
	if ( focusedKey.value !== null && flatItems.value.some( ( item ) => item.key === focusedKey.value ) ) {
		return focusedKey.value;
	}
	// WAI-ARIA APG: roving focus starts on the active item, so tabbing in lands there rather
	// than at an unrelated shallow sibling.
	const activeItem = flatItems.value.find( ( item ) => item.active === true );
	return activeItem?.key ?? flatItems.value[ 0 ]?.key ?? null;
} );

// A mouse selection moves the tab stop too, or Shift+Tab back into the tree would land on
// the row an earlier arrow key left.
function selectItem( item: NeoTreeItem<T> ): void {
	focusedKey.value = item.key;
	emit( 'select', item );
}

// The tab stop moves here too, and for the same reason a click does: whichever control the
// pointer used, Shift+Tab back into the tree should land on the row it was last used on.
function toggleItem( item: NeoTreeItem<T> ): void {
	focusedKey.value = item.key;
	emit( 'toggle', item );
}

// Enter and Space are handled here because a treeitem contains its own child group and so cannot
// be a button. Left and Right follow the tree pattern's two-step contract: Right opens a closed
// node and then descends into an open one, Left closes an open node and then climbs out.
function onKeydown( event: KeyboardEvent, item: NeoTreeItem<T> ): void {
	const keys = flatItems.value.map( ( flatItem ) => flatItem.key );
	const currentIndex = keys.indexOf( item.key );
	let nextIndex: number;

	switch ( event.key ) {
		case 'Enter':
		case ' ':
			event.preventDefault();
			// Handled here; an ancestor item must not act on its descendant's key press.
			event.stopPropagation();
			selectItem( item );
			return;
		case 'ArrowRight':
			event.preventDefault();
			event.stopPropagation();
			if ( isTogglable( item ) && !isExpanded( item ) ) {
				toggleItem( item );
			} else if ( isExpanded( item ) && ( item.children ?? [] ).length > 0 ) {
				focusKey( ( item.children ?? [] )[ 0 ].key );
			}
			return;
		case 'ArrowLeft': {
			event.preventDefault();
			event.stopPropagation();
			if ( isTogglable( item ) && isExpanded( item ) ) {
				toggleItem( item );
				return;
			}
			const parentKey = printedItems.value[ currentIndex ]?.parentKey ?? null;
			if ( parentKey !== null ) {
				focusKey( parentKey );
			}
			return;
		}
		case 'ArrowDown':
			nextIndex = ( currentIndex + 1 ) % keys.length;
			break;
		case 'ArrowUp':
			nextIndex = ( currentIndex - 1 + keys.length ) % keys.length;
			break;
		case 'Home':
			nextIndex = 0;
			break;
		case 'End':
			nextIndex = keys.length - 1;
			break;
		default:
			return;
	}

	event.preventDefault();
	event.stopPropagation();
	focusKey( keys[ nextIndex ] );
}

// The tab stop and the DOM focus move together; the element is found after the render, since
// closing a node renumbers every id below it.
function focusKey( key: string ): void {
	focusedKey.value = key;
	nextTick( () => {
		const index = flatItems.value.findIndex( ( item ) => item.key === key );
		if ( index !== -1 ) {
			document.getElementById( elementId( index ) )?.focus();
		}
	} );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-tree {
	box-sizing: @box-sizing-base;
	/* A panel of dense rows rather than body text, so it sets its own base a step down. */
	font-size: @font-size-small;

	&__list,
	&__group {
		margin: 0;
		padding: 0;
		list-style: none;
	}

	/* One guide line per level, running the full height of that level, captions included —
		broken only where a disclosure control sits on it, which the control does itself.
		The indent has to hold both the line and a control wide enough to press: a 24px control
		centred on the line reaches 12px into the level above, so 16px leaves 4px between it and
		that level's own line. */
	&__relation {
		margin-inline-start: @spacing-100;
		border-inline-start: @border-subtle;
	}

	/* The line-height is set rather than inherited: left to the skin it is whatever that
		skin says. */
	&__edge,
	&__node-caption {
		font-size: @font-size-x-small;
		line-height: @line-height-xx-small;
		color: @color-subtle;
	}

	/* A caption starts where the labels it heads start, which is clear of the control rather
		than at the row's own padding. Browser-measured to the same x as its labels. */
	&__edge {
		display: block;
		padding: @spacing-30 @spacing-35 @spacing-12;
		padding-inline-start: @ext-neowiki-tree-text-inset;
	}

	&__node {
		/* The focusable node contains its whole subtree, so the ring goes on the row instead. */
		&:focus-visible {
			outline: 0;
		}
	}

	&__node-name,
	&__node-line {
		display: flex;
		align-items: center;
		gap: @spacing-25;
	}

	&__node-name {
		box-sizing: @box-sizing-base;
		/* Positions the disclosure control against the row rather than in it. */
		position: relative;
		width: @size-full;
		min-height: @size-200;
		/* The block padding is absorbed by min-height until a row's content takes two lines. */
		padding: @spacing-12 @spacing-35;
		/* Clear of the control, which straddles the guide line and so reaches into the row. Carried
			by every row rather than reserved on the rows that have one, so a row with nothing to
			open draws nothing and the labels still line up down the level. */
		padding-inline-start: @ext-neowiki-tree-text-inset;
		background-color: @background-color-transparent;
		border-radius: @border-radius-base;
		/* The row is what selects; the rest of the <li> is its subtree and is not clickable. */
		cursor: pointer;
		transition-property: @transition-property-base;
		transition-duration: @transition-duration-base;

		&:hover {
			background-color: @background-color-button-quiet--hover;
		}
	}

	&__node:focus-visible > &__node-name {
		outline: @border-width-thick solid @outline-color-progressive--focus;
	}

	/* Codex's selected-menu-item state: background and colour, and no weight — a weight that
		changed with the state would re-measure the row under the pointer that selected it. */
	&__node--active > &__node-name {
		background-color: @background-color-progressive-subtle;
		color: @color-progressive;
	}

	/* Matched through the node rather than nested in `&__node-name`, where it would tie the
		active rule above on specificity and lose to it on source order, leaving the selected
		node showing nothing on a press. */
	&__node > &__node-name:active {
		background-color: @background-color-button-quiet--active;
		color: @color-emphasized;
	}

	/* Two targets, one lit at a time. `:hover` matches an ancestor of whatever the pointer is
		over and the control is a descendant of the row, so without this the row lights up from the
		gutter — saying "pressing this selects the Subject" at the one place where it does not.
		Selected stays lit: that is where the reader is, not what they are about to do. */
	&__node > &__node-name:has( > &__twisty:hover ),
	&__node > &__node-name:has( > &__twisty:active ):active {
		background-color: @background-color-transparent;
	}

	&__node--active > &__node-name:has( > &__twisty:hover ) {
		background-color: @background-color-progressive-subtle;
	}

	/* Out of the row's flow and over the guide line running down the level, so a row with
		nothing to open reserves nothing and every label at a level starts at the same place.
		Half its width to the start of the row centres it on that 1px line. */
	&__twisty {
		position: absolute;
		inset-inline-start: calc( -1 * @size-150 / 2 - @border-width-base );
		/* 24 by 32 rather than 24 square: the rows are contiguous, so a reader aiming at a chevron
			must not be able to fall between two of them. What is DRAWN is the 24px box inside. */
		top: 0;
		height: @size-200;
		width: @size-150;
		display: flex;
		align-items: center;
		justify-content: center;
		color: @color-subtle;
		cursor: pointer;
		/* Not text: a press on it should open the branch, not begin selecting the name beside it. */
		user-select: none;
		/* Load-bearing, and not for ordering: any z-index makes this a stacking context, which is
			what keeps the two `z-index: -1` layers below the icon inside the control rather than
			behind the guide line they exist to cover. Without it the break paints invisibly. */
		z-index: 1;

		/* The break in the line: one pixel wide and on the line itself, since the control's other
			half lies over the row where the ground belongs to the row's own state. 24px tall, so
			the line still shows between two controls on adjacent rows. */
		&::before {
			content: '';
			position: absolute;
			inset-block: calc( ( @size-200 - @size-150 ) / 2 );
			inset-inline-start: calc( @size-150 / 2 );
			width: @border-width-base;
			background-color: @background-color-base;
			z-index: -1;
		}

		/* The chip: what the reader sees and aims at, inside the taller target. Translucent, so
			it tints the selected row's ground rather than patching over it. */
		&::after {
			content: '';
			position: absolute;
			inset-inline: 0;
			inset-block: calc( ( @size-200 - @size-150 ) / 2 );
			border-radius: @border-radius-base;
			z-index: -1;
		}

		&:hover {
			color: @color-base;
		}

		&:hover::after {
			background-color: @background-color-button-quiet--hover;
		}
	}

	/* Rows keep a common height, so a name gives way at its end. */
	&__node-label {
		min-width: 0;
		font-weight: @font-weight-bold;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	/* Never abbreviated, as on its own line it never was: property names are authored on-wiki. */
	&__node-caption {
		min-width: 0;
	}

	/* The figure keeps the row's own size rather than stepping down: a smaller one needs a
		line-height of its own to sit level with the label, and an inherited unitless one scales
		with the font, so the two boxes stop matching and the row's centring lifts the digit.
		Weight and colour set it back instead. Tabular figures, so a column does not shift. */
	&__count,
	&__mark {
		flex: 0 0 auto;
		margin-inline-start: auto;
		color: @color-subtle;
	}

	&__count {
		font-weight: @font-weight-normal;
		font-feature-settings: 'tnum';
	}

	&__mark--unreadable {
		color: @color-warning;
	}

	/* Transparent until a row folds, where it becomes the one item a wrap may move: otherwise
		the trailing slot is left on a line of its own once the name has taken one. */
	&__node-line {
		display: contents;
	}

	/* The basis is the name's own `max-content` width, so the line breaks exactly when the
		caption and the whole name cannot share it. A proportional basis gets both ends wrong. */
	&__node-name--folded {
		flex-wrap: wrap;
		/* The boxes differ in height, so centring them leaves the text off by half of it. */
		align-items: baseline;
		align-content: center;
		gap: 0 @spacing-50;
		/* Room for what the rule below pins there, derived from where it pins it: the inset plus
			two figures. `ch` is a digit's advance and the count is tabular at the row's size, so
			this holds to 99, past which the digits reach into the caption. */
		padding-inline-end: calc( @spacing-35 + 2ch );
	}

	/* This is the row that wraps, so the count cannot be a flex item on it: an auto margin would
		make it the one item the wrap moves, landing it under the name. Pinned out of the flow
		instead, in the room reserved above. */
	&__node-name--folded > &__count,
	&__node-name--folded > &__mark {
		position: absolute;
		inset-inline-end: @spacing-35;
		margin-inline-start: 0;
		/* A line box of its own, since out of the flow it can no longer take the row's centring:
			inset by half the row's spare height, so it sits on the first line either way. */
		top: calc( ( @size-200 - @size-150 ) / 2 );
		height: @size-150;
		line-height: @size-150;
	}

	&__node-name--folded > &__node-line {
		display: flex;
		align-items: center;
		gap: @spacing-25;
		min-width: 0;
		flex-basis: max-content;
	}
}
</style>
