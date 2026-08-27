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
				:keydown="onKeydown"
			>
				<template #trailing="slotProps">
					<slot
						name="trailing"
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
import type { NeoTreeItem } from './NeoTreeModel.ts';

const props = defineProps<{
	items: NeoTreeItem<T>[];
	label: string;
	idPrefix?: string;
}>();

const emit = defineEmits<{
	select: [ NeoTreeItem<T> ];
}>();

// Declared out here: a parenthesis anywhere inside a macro's type argument trips ESLint's
// func-call-spacing, which reads it as the macro's own call.
type TrailingSlot = ( slotProps: { item: NeoTreeItem<T> } ) => unknown;

defineSlots<{
	trailing?: TrailingSlot;
}>();

// Printed order, which is both the order Up/Down move through and the order the element ids
// are numbered in. A top-level item's `groupLabel` has nowhere to print: a caption may not sit
// inside the tree container itself.
const flatItems = computed( (): NeoTreeItem<T>[] => {
	const items: NeoTreeItem<T>[] = [];

	for ( const item of props.items ) {
		items.push( ...flatten( item ) );
	}

	return items;
} );

function flatten( item: NeoTreeItem<T> ): NeoTreeItem<T>[] {
	const items: NeoTreeItem<T>[] = [ item ];

	for ( const child of item.children ?? [] ) {
		items.push( ...flatten( child ) );
	}

	return items;
}

const elementIds = computed( (): ReadonlyMap<string, string> =>
	new Map( flatItems.value.map( ( item, index ) => [ item.key, elementId( index ) ] ) ) );

function elementId( index: number ): string {
	return `${ props.idPrefix ?? 'ext-neowiki-tree' }-node-${ index }`;
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

// The tree is eagerly expanded and offers no per-item toggle, so Left/Right have nothing to
// do. Enter and Space have to be handled here: a treeitem contains its own child group, so it
// cannot be a button.
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
	focusedKey.value = keys[ nextIndex ];
	nextTick( () => {
		document.getElementById( elementId( nextIndex ) )?.focus();
	} );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

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

	&__list {
		padding-block: @spacing-100;
	}

	/* One guide line per level, running the full height of that level, captions included. */
	&__relation {
		margin-inline-start: @spacing-75;
		border-inline-start: @border-subtle;
	}

	/* The inline padding matches a row's, so a caption starts where the node labels start. */
	&__edge {
		display: block;
		padding: @spacing-30 @spacing-35 @spacing-12;
		font-size: @font-size-x-small;
		color: @color-subtle;
	}

	&__node {
		/* The focusable node contains its whole subtree, so the ring goes on the row instead. */
		&:focus-visible {
			outline: 0;
		}
	}

	&__node-name {
		display: flex;
		align-items: center;
		gap: @spacing-25;
		box-sizing: @box-sizing-base;
		width: @size-full;
		min-height: @size-200;
		padding: 0 @spacing-35;
		background-color: @background-color-transparent;
		border-radius: @border-radius-base;
		/* Only the row selects; the rest of the <li> is the subtree, which is not clickable. */
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

	&__node-label {
		min-width: 0;
		font-weight: @font-weight-bold;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	/* Set apart by colour rather than size: the row already sits a step below body text. */
	&__node-secondary {
		flex: none;
		color: @color-subtle;
	}
}
</style>
