<!-- A group's caption names the group through `aria-labelledby`, and is `aria-hidden`
	because only `treeitem` and `group` may be children of a tree. -->
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
		:aria-expanded="groups.length > 0 ? 'true' : undefined"
		:tabindex="rovingKey === item.key ? 0 : -1"
		v-bind="item.attrs"
		@keydown="keydown( $event, item )"
	>
		<span
			:id="`${ elementId }-name`"
			class="ext-neowiki-tree__node-name"
			@click.stop="select( item )"
		>
			<span class="ext-neowiki-tree__node-label">{{ item.label }}</span>
			<span
				v-if="item.secondaryLabel"
				class="ext-neowiki-tree__node-secondary"
			><slot
				name="secondary"
				:item="item"
			>{{ item.secondaryLabel }}</slot></span>
			<slot
				name="trailing"
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
				v-if="group.label !== undefined"
				:id="`${ groupId( index ) }-label`"
				class="ext-neowiki-tree__edge"
				aria-hidden="true"
			>{{ group.label }}</span>

			<ul
				:id="groupId( index )"
				class="ext-neowiki-tree__group"
				role="group"
				:aria-labelledby="group.label === undefined ? undefined : `${ groupId( index ) }-label`"
			>
				<NeoTreeNode
					v-for="child in group.items"
					:key="child.key"
					:item="child"
					:element-ids="elementIds"
					:roving-key="rovingKey"
					:select="select"
					:keydown="keydown"
				>
					<template #secondary="slotProps">
						<slot
							name="secondary"
							v-bind="slotProps"
						/>
					</template>

					<template #trailing="slotProps">
						<slot
							name="trailing"
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
import type { NeoTreeItem } from './NeoTreeModel.ts';

// Declared out here for the reason given in NeoTree.vue.
type NodeSelect = ( item: NeoTreeItem<T> ) => void;
type NodeKeydown = ( event: KeyboardEvent, item: NeoTreeItem<T> ) => void;
type NodeSlot = ( slotProps: { item: NeoTreeItem<T> } ) => unknown;

const props = defineProps<{
	item: NeoTreeItem<T>;
	// Minted by the tree over the whole flattened list: a node cannot see its own position in it.
	elementIds: ReadonlyMap<string, string>;
	rovingKey: string | null;
	select: NodeSelect;
	keydown: NodeKeydown;
}>();

defineSlots<{
	secondary?: NodeSlot;
	trailing?: NodeSlot;
}>();

const elementId = computed( (): string => props.elementIds.get( props.item.key ) ?? '' );

interface RenderGroup {
	label: string | undefined;
	items: NeoTreeItem<T>[];
}

// Contiguous children sharing a caption form one group; an unchanged caption continues it,
// and children with no caption group the same way into a container that prints none.
const groups = computed( (): RenderGroup[] => {
	const rendered: RenderGroup[] = [];

	for ( const child of props.item.children ?? [] ) {
		const lastGroup = rendered[ rendered.length - 1 ];

		if ( lastGroup !== undefined && lastGroup.label === child.groupLabel ) {
			lastGroup.items.push( child );
			continue;
		}

		rendered.push( { label: child.groupLabel, items: [ child ] } );
	}

	return rendered;
} );

function groupId( index: number ): string {
	return `${ elementId.value }-group-${ index }`;
}
</script>
