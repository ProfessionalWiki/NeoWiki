<!-- A group's caption names the group through `aria-labelledby`, and is `aria-hidden` because
	only `treeitem` and `group` may be children of a tree. A caption heading a single row is
	folded onto that row instead, where it names the treeitem directly. -->
<template>
	<li
		:id="elementId"
		class="ext-neowiki-tree__node"
		:class="{
			'ext-neowiki-tree__node--active': item.active === true
		}"
		role="treeitem"
		:aria-labelledby="`${ elementId }-name`"
		:aria-selected="item.active === true"
		:aria-expanded="expandable ? expanded : undefined"
		:tabindex="rovingKey === item.key ? 0 : -1"
		v-bind="item.attrs"
		@keydown="keydown( $event, item )"
	>
		<span
			:id="`${ elementId }-name`"
			class="ext-neowiki-tree__node-name"
			:class="{ 'ext-neowiki-tree__node-name--folded': folded }"
			@click.stop="select( item )"
		>
			<!-- `aria-hidden` and unfocusable: the state it would announce is on the treeitem
				already, and a tree has one tab stop. The press stops here, since expanding is not
				selecting and only one of the two loads a Subject.

				Laid over the guide line rather than given a column, so a row with nothing to open
				reserves nothing and every label at a level starts at the same place. -->
			<span
				v-if="togglable"
				class="ext-neowiki-tree__twisty"
				aria-hidden="true"
				@click.stop="toggle( item )"
			>
				<CdxIcon
					:icon="expanded ? cdxIconExpand : cdxIconNext"
					size="x-small"
				/>
			</span>

			<span
				v-if="folded"
				class="ext-neowiki-tree__node-caption"
			>{{ item.groupLabel }}</span>

			<!-- One flex item, so a wrap cannot leave the trailing slot on a line of its own. -->
			<span class="ext-neowiki-tree__node-line">
				<span class="ext-neowiki-tree__node-label">{{ item.label }}</span>
				<slot
					name="trailing"
					:item="item"
				/>
			</span>

			<!-- Outside the line above, so a wrap cannot carry it off with the name. -->
			<slot
				name="end"
				:item="item"
			/>
		</span>

		<div
			v-for="( group, index ) in groups"
			:key="index"
			class="ext-neowiki-tree__relation"
			role="none"
		>
			<span
				v-if="group.captionOnItsOwnLine"
				:id="`${ groupId( index ) }-label`"
				class="ext-neowiki-tree__edge"
				aria-hidden="true"
			>{{ group.label }}</span>

			<ul
				:id="groupId( index )"
				class="ext-neowiki-tree__group"
				role="group"
				:aria-labelledby="group.captionOnItsOwnLine ? `${ groupId( index ) }-label` : undefined"
			>
				<NeoTreeNode
					v-for="child in group.items"
					:key="child.key"
					:item="child"
					:folded="group.captionOnTheRow"
					:element-ids="elementIds"
					:roving-key="rovingKey"
					:select="select"
					:toggle="toggle"
					:keydown="keydown"
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
		</div>
	</li>
</template>

<script setup lang="ts" generic="T">
import { computed } from 'vue';
import { CdxIcon } from '@wikimedia/codex';
import { cdxIconExpand, cdxIconNext } from '@wikimedia/codex-icons';
import { isExpandable, isExpanded, isTogglable } from './NeoTreeModel.ts';
import type { NeoTreeItem } from './NeoTreeModel.ts';

// Declared out here for the reason given in NeoTree.vue.
type NodeSelect = ( item: NeoTreeItem<T> ) => void;
type NodeToggle = ( item: NeoTreeItem<T> ) => void;
type NodeKeydown = ( event: KeyboardEvent, item: NeoTreeItem<T> ) => void;
type NodeSlot = ( slotProps: { item: NeoTreeItem<T> } ) => unknown;

const props = defineProps<{
	item: NeoTreeItem<T>;
	// Whether this node prints its own `groupLabel` on its row. Only the parent knows, because
	// it depends on how many siblings share that caption.
	folded?: boolean;
	// Minted by the tree over the whole flattened list: a node cannot see its own position in it.
	elementIds: ReadonlyMap<string, string>;
	rovingKey: string | null;
	select: NodeSelect;
	toggle: NodeToggle;
	keydown: NodeKeydown;
}>();

defineSlots<{
	trailing?: NodeSlot;
	end?: NodeSlot;
}>();

const elementId = computed( (): string => props.elementIds.get( props.item.key ) ?? '' );

// Read through the shared rules, which the tree reads too: what this renders and what the tree
// prints have to agree.
const expandable = computed( (): boolean => isExpandable( props.item ) );

const expanded = computed( (): boolean => isExpanded( props.item ) );

const togglable = computed( (): boolean => isTogglable( props.item ) );

interface RenderGroup {
	label: string | undefined;
	items: NeoTreeItem<T>[];
	// Over several rows a caption keeps its line; over one it is a line of chrome introducing a
	// line of content, so it moves onto that row. Decided here, where the run's size is known.
	captionOnItsOwnLine: boolean;
	captionOnTheRow: boolean;
}

// Contiguous children sharing a caption form one group; children with no caption group the same
// way into a container that prints none. A collapsed item prints none of them whether or not it
// holds any, so the arrow keys can never reach an unrendered row.
const groups = computed( (): RenderGroup[] => {
	const rendered: RenderGroup[] = [];

	for ( const child of expanded.value ? props.item.children ?? [] : [] ) {
		const lastGroup = rendered[ rendered.length - 1 ];

		if ( lastGroup !== undefined && lastGroup.label === child.groupLabel ) {
			lastGroup.items.push( child );
			continue;
		}

		rendered.push( {
			label: child.groupLabel,
			items: [ child ],
			captionOnItsOwnLine: false,
			captionOnTheRow: false
		} );
	}

	for ( const group of rendered ) {
		group.captionOnItsOwnLine = Boolean( group.label ) && group.items.length > 1;
		group.captionOnTheRow = Boolean( group.label ) && group.items.length === 1;
	}

	return rendered;
} );

function groupId( index: number ): string {
	return `${ elementId.value }-group-${ index }`;
}
</script>
