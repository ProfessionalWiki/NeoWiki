<template>
	<div class="ext-neowiki-schema-editor__property-list">
		<ul
			ref="listRef"
			role="listbox"
			class="ext-neowiki-property-list"
			@keydown="onKeydown"
		>
			<EditorListItem
				class="ext-neowiki-property-list__item ext-neowiki-property-list__item--schema"
				v-bind="rowState( SCHEMA_POSITION )"
				@click="selectAndFocus( SCHEMA_POSITION )"
			>
				<template #icon>
					<CdxIcon
						class="ext-neowiki-property-list__item__icon"
						:icon="cdxIconSettings"
					/>
				</template>
				{{ $i18n( 'neowiki-schema-editor-schema' ).text() }}
			</EditorListItem>
			<EditorListItem
				v-if="props.showsLabelRow"
				class="ext-neowiki-property-list__item"
				v-bind="rowState( LABEL_POSITION )"
				@click="selectAndFocus( LABEL_POSITION )"
			>
				<template #icon>
					<CdxIcon
						class="ext-neowiki-property-list__item__icon"
						:icon="cdxIconTag"
					/>
				</template>
				{{ $i18n( 'neowiki-schema-editor-subject-label' ).text() }}
				<template #description>
					<span class="ext-neowiki-property-list__item__description">
						{{ describeType( TextType.typeName, false ) }}
					</span>
				</template>
			</EditorListItem>
			<EditorListItem
				v-for="( property, index ) in propertyArray"
				:key="property.name.toString()"
				class="ext-neowiki-property-list__item ext-neowiki-property-list__item--property"
				v-bind="rowState( propertyPosition( index ) )"
				@click="selectAndFocus( propertyPosition( index ) )"
			>
				<template
					v-if="getPropertyIcon( property )"
					#icon
				>
					<CdxIcon
						class="ext-neowiki-property-list__item__icon"
						:icon="getPropertyIcon( property )!"
					/>
				</template>
				{{ property.name.toString() }}
				<template
					v-if="describeType( property.type, property.required )"
					#description
				>
					<span class="ext-neowiki-property-list__item__description">
						{{ describeType( property.type, property.required ) }}
					</span>
				</template>
				<template #actions>
					<CdxButton
						:aria-label="$i18n( 'neowiki-schema-editor-delete-property' ).text()"
						weight="quiet"
						action="destructive"
						@click.stop="emit( 'propertyDeleted', property.name )"
					>
						<CdxIcon :icon="cdxIconTrash" />
					</CdxButton>
					<ReorderHandle
						:position="index"
						:count="propertyArray.length"
						:label="reorderHandleLabel( property.name.toString() )"
						@move="moveProperty( index, $event )"
					/>
				</template>
			</EditorListItem>
		</ul>
		<button
			class="ext-neowiki-property-list__add-item"
			type="button"
			:title="$i18n( 'neowiki-schema-editor-new-property-shortcut' ).text()"
			aria-keyshortcuts="Control+Enter Meta+Enter"
			@click="emit( 'addProperty' )"
		>
			<CdxIcon
				class="ext-neowiki-property-list__item__icon"
				:icon="cdxIconAdd"
			/>
			<span class="ext-neowiki-property-list__add-item__label">
				{{ $i18n( 'neowiki-schema-editor-new-property' ).text() }}
			</span>
		</button>
		<LiveAnnouncement ref="announcementRef" />
	</div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconSettings, cdxIconTag, cdxIconTrash } from '@wikimedia/codex-icons';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useSortable } from '@/composables/useSortable.ts';
import ReorderHandle from '@/components/common/ReorderHandle.vue';
import LiveAnnouncement, { type LiveAnnouncementExposes } from '@/components/common/LiveAnnouncement.vue';
import { movedAnnouncement, reorderHandleLabel, withItemMoved } from '@/components/common/reordering.ts';
import EditorListItem from '@/components/common/EditorListItem.vue';
import type { Icon } from '@wikimedia/codex-icons';
import { LABEL_ROW, propertyRow, SCHEMA_ROW, type PropertyListRow } from '@/components/SchemaEditor/PropertyListRow.ts';

const props = defineProps<{
	properties: PropertyDefinitionList;
	selection: PropertyListRow;
	showsLabelRow: boolean;
}>();

const emit = defineEmits<{
	select: [ row: PropertyListRow ];
	addProperty: [];
	propertyDeleted: [ name: PropertyName ];
	propertyReordered: [ names: PropertyName[] ];
}>();

const componentRegistry = NeoWikiServices.getComponentRegistry();

const listRef = ref<HTMLElement | null>( null );
const announcementRef = ref<LiveAnnouncementExposes | null>( null );

const propertyArray = computed( (): PropertyDefinition[] => [ ...props.properties ] );

// Positions count the rows as the list shows them: the Schema's first, then the Label row where it is shown, then
// the properties.
const SCHEMA_POSITION = 0;
const LABEL_POSITION = 1;

const firstPropertyPosition = computed( (): number => props.showsLabelRow ? 2 : 1 );

const lastPosition = computed( (): number => firstPropertyPosition.value + propertyArray.value.length - 1 );

function propertyPosition( index: number ): number {
	return firstPropertyPosition.value + index;
}

function propertyIndex( position: number ): number {
	return position - firstPropertyPosition.value;
}

function isPropertyPosition( position: number ): boolean {
	return position >= firstPropertyPosition.value && position <= lastPosition.value;
}

const selectedPosition = computed( (): number => {
	const selection = props.selection;

	if ( selection.kind === 'schema' ) {
		return SCHEMA_POSITION;
	}

	if ( selection.kind === 'subjectLabel' ) {
		return props.showsLabelRow ? LABEL_POSITION : -1;
	}

	const index = propertyArray.value.findIndex( ( property ) => property.name.toString() === selection.name );
	return index === -1 ? -1 : propertyPosition( index );
} );

// The selected row is the list's Tab stop, except alone: it has no other row to move to, so Tab passes the list.
function rowState( position: number ): { selected: boolean; tabindex: number } {
	const selected = position === selectedPosition.value;

	return {
		selected,
		tabindex: selected && lastPosition.value > SCHEMA_POSITION ? 0 : -1
	};
}

function getPropertyIcon( property: PropertyDefinition ): Icon | undefined {
	return componentRegistry.getIcon( property.type );
}

function describeType( type: string, required: boolean ): string {
	const typeLabel = mw.msg( componentRegistry.getLabel( type ) );
	return required ? typeLabel : mw.msg( 'neowiki-schema-editor-optional-property-type', typeLabel );
}

// The focus follows the selection the parent makes of it, which the parent may refuse.
async function selectAndFocus( position: number ): Promise<void> {
	emit( 'select', rowAt( position ) );
	await focusSelectedRow();
}

function rowAt( position: number ): PropertyListRow {
	if ( position === SCHEMA_POSITION ) {
		return SCHEMA_ROW;
	}

	if ( isPropertyPosition( position ) ) {
		return propertyRow( propertyArray.value[ propertyIndex( position ) ].name.toString() );
	}

	return LABEL_ROW;
}

async function focusSelectedRow(): Promise<void> {
	await nextTick();
	listRef.value?.querySelectorAll<HTMLElement>( ':scope > [role="option"]' )[ selectedPosition.value ]?.focus();
}

function moveProperty( fromIndex: number, toIndex: number ): void {
	const names = withItemMoved( propertyArray.value.map( ( p ) => p.name ), fromIndex, toIndex );

	emit( 'propertyReordered', names );
	announcementRef.value?.announce( movedAnnouncement( names[ toIndex ].toString(), toIndex, names.length ) );
}

// Among the properties only: the rows above them stay where they are.
function moveSelectedProperty( by: 1 | -1 ): void {
	const position = selectedPosition.value;

	if ( isPropertyPosition( position ) && isPropertyPosition( position + by ) ) {
		moveProperty( propertyIndex( position ), propertyIndex( position ) + by );
		focusSelectedRow();
	}
}

function onKeydown( event: KeyboardEvent ): void {
	const position = selectedPosition.value;

	if ( event.key === 'ArrowDown' ) {
		event.preventDefault();
		if ( event.altKey ) {
			moveSelectedProperty( 1 );
		} else if ( position < lastPosition.value ) {
			selectAndFocus( position + 1 );
		}
	} else if ( event.key === 'ArrowUp' ) {
		event.preventDefault();
		if ( event.altKey ) {
			moveSelectedProperty( -1 );
		} else if ( position > SCHEMA_POSITION ) {
			selectAndFocus( position - 1 );
		}
	}
}

useSortable( listRef, {
	draggable: '.ext-neowiki-property-list__item--property',
	onReorder( oldIndex: number, newIndex: number ): void {
		// SortableJS indexes count the rows above the properties, which stay where they are.
		moveProperty( propertyIndex( oldIndex ), propertyIndex( newIndex ) );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

/* useSortable's default ghost class, so for every list that keeps it, extensions' lists among them. */
.ext-neowiki-property-list__item--ghost {
	opacity: 0.5;
	background-color: @background-color-interactive-subtle;
}

.ext-neowiki-property-list {
	list-style: none;
	margin: 0;
	padding: 0;

	/* Compounded to outrank the shared row's own rules, whichever stylesheet loads first. */
	& > &__item {
		user-select: none;
	}

	& > &__item--property {
		cursor: grab;
	}

	/* Set apart from the rows of what the Schema's Subjects hold by a rule in the gap below it, inset as the rows'
		content is, and in a grey lighter than lines that divide the dialog. Drawn outside the row, which has no
		actions to clip. */
	& > &__item--schema {
		overflow: visible;
		margin-block-end: @spacing-50;

		&::after {
			content: '';
			position: absolute;
			inset-block-start: calc( 100% + @spacing-25 );
			inset-inline: @spacing-75;
			border-block-start: @border-width-base @border-style-base @border-color-muted;
		}
	}

	& > &__item--ghost {
		--ext-neowiki-editor-list-item-background: @background-color-interactive-subtle;
	}

	&__item__icon.cdx-icon {
		background-position: @background-position-base;
		background-repeat: no-repeat;
		background-size: @background-size-search-figure;
		background-color: @background-color-interactive-subtle;
		flex-shrink: 0;
		box-sizing: @box-sizing-base;
		min-width: @min-size-search-figure;
		min-height: @min-size-search-figure;
		width: @size-search-figure;
		height: @size-search-figure;
		border: @border-subtle;
		border-radius: @border-radius-base;
		color: @color-subtle;

		svg {
			min-width: @min-size-icon-medium;
			min-height: @min-size-icon-medium;
			width: @size-icon-medium;
			height: @size-icon-medium;
		}
	}

	&__item__description {
		max-width: 100%;
		font-size: @font-size-small;
		color: @color-subtle;
		overflow: hidden;
		text-overflow: ellipsis;
		white-space: nowrap;
	}

	&__add-item {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		padding: @spacing-50 @spacing-75;
		border: 0;
		border-radius: @border-radius-base;
		background: none;
		width: 100%;
		cursor: pointer;
		font: inherit;
		color: inherit;
		text-align: start;
		margin-block-start: @spacing-100;

		&:hover {
			background-color: @background-color-interactive-subtle;
		}

		&__label {
			overflow: hidden;
			text-overflow: ellipsis;
			white-space: nowrap;
		}

		/* Inside the row: the pane around the list clips at the dialog's edge. */
		&:focus-visible {
			outline: @border-width-thick solid @outline-color-progressive--focus;
			outline-offset: -@border-width-thick;
		}
	}
}
</style>
