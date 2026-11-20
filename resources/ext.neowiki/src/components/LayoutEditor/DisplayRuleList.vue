<template>
	<div
		ref="listRoot"
		class="ext-neowiki-display-rule-list"
	>
		<div
			v-for="group in groups"
			:key="group.label"
			class="ext-neowiki-display-rule-list__group"
		>
			<div class="ext-neowiki-display-rule-list__header">
				<span class="ext-neowiki-display-rule-list__header__title">
					{{ $i18n( group.label ).text() }}
				</span>
				<span
					v-if="group.shown && isDefault"
					class="ext-neowiki-display-rule-list__header__note"
				>
					<template v-if="propertyNamesLeftOut.length === 0">
						{{ $i18n( 'neowiki-layout-display-no-rules' ).text() }}
					</template>
					<template v-else>
						{{ $i18n(
							'neowiki-layout-display-no-rules-dependent',
							propertyNamesLeftOut.join( $i18n( 'comma-separator' ).text() ),
							propertyNamesLeftOut.length
						).text() }}
					</template>
				</span>
				<span
					v-if="!group.shown"
					class="ext-neowiki-display-rule-list__header__end"
				>
					<CdxButton
						class="ext-neowiki-display-rule-list__reset"
						weight="quiet"
						@click="onShowAll"
					>
						{{ $i18n( 'neowiki-layout-editor-show-all' ).text() }}
					</CdxButton>
				</span>
			</div>
			<ul
				:ref="group.shown ? keepShownList : keepHiddenList"
				:role="props.preview ? 'listbox' : 'list'"
				:aria-label="$i18n( group.label ).text()"
				class="ext-neowiki-display-rule-list__items"
			>
				<EditorListItem
					v-for="( row, index ) in group.rows"
					:key="row.property.name.toString()"
					class="ext-neowiki-display-rule-list__item"
					:class="{
						'ext-neowiki-display-rule-list__item--hidden': !row.shown,
						'ext-neowiki-display-rule-list__item--hovered': isHovered( row )
					}"
					:data-mw-neowiki-property="row.property.name.toString()"
					:selected="isSelected( row )"
					:status="isUnsaved( row ) ? UNSAVED : null"
					:unselectable="!props.preview"
					:tabindex="props.preview ? ( tabStops.has( row ) ? 0 : -1 ) : undefined"
					@click="onRowActivated( row )"
					@keydown="onRowKeydown( $event, group.rows, index )"
					@mouseenter="emit( 'hover', row.property.name.toString() )"
					@mouseleave="emit( 'hover', null )"
				>
					<template #icon>
						<CdxIcon
							class="ext-neowiki-display-rule-list__item__type"
							:icon="componentRegistry.getIcon( row.property.type )"
						/>
					</template>
					<span class="ext-neowiki-display-rule-list__item__name">
						{{ row.property.name.toString() }}
					</span>
					<template
						v-if="opensDisplayAttributes( row )"
						#end
					>
						<CdxIcon
							:icon="cdxIconNext"
							size="small"
						/>
					</template>
					<template #actions>
						<!-- The row's own click selects it or opens its Display Attributes, which a click on a control in it must not. -->
						<span
							class="ext-neowiki-display-rule-list__item__actions"
							@click.stop
						>
							<span
								class="ext-neowiki-display-rule-list__item__action-tooltip"
								:title="$i18n( toggleMessageKey( row.shown ) ).text()"
							>
								<CdxButton
									class="ext-neowiki-display-rule-list__item__action"
									weight="quiet"
									:disabled="lastShown( row.shown )"
									:aria-label="$i18n( toggleMessageKey( row.shown ) ).text()"
									@click="onToggle( row.property.name.toString() )"
								>
									<CdxIcon :icon="row.shown ? cdxIconEye : cdxIconEyeClosed" />
								</CdxButton>
							</span>
							<ReorderHandle
								v-if="row.shown"
								:position="index"
								:count="shownCount"
								:label="reorderHandleLabel( row.property.name.toString() )"
								@move="moveRow( index, $event )"
							/>
							<!-- Hidden properties keep the Schema's order, so their handle only drags them out to be shown. -->
							<span
								v-else
								class="ext-neowiki-display-rule-list__item__drag"
								aria-hidden="true"
							>
								<CdxIcon :icon="cdxIconDraggable" />
							</span>
						</span>
					</template>
				</EditorListItem>
			</ul>
		</div>
		<LiveAnnouncement ref="announcementRef" />
	</div>
</template>

<script setup lang="ts">
import { ref, computed, getCurrentInstance, nextTick, type ComponentPublicInstance } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconDraggable, cdxIconEye, cdxIconEyeClosed, cdxIconNext } from '@wikimedia/codex-icons';
import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import type { DisplayRule } from '@/domain/Layout.ts';
import { useSortable } from '@/composables/useSortable.ts';
import ReorderHandle from '@/components/common/ReorderHandle.vue';
import LiveAnnouncement, { type LiveAnnouncementExposes } from '@/components/common/LiveAnnouncement.vue';
import { movedAnnouncement, REORDER_HANDLE_SELECTOR, reorderHandleLabel } from '@/components/common/reordering.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import EditorListItem from '@/components/common/EditorListItem.vue';
import { UNSAVED } from '@/components/common/ItemStatus.ts';
import { unifiedRows, rulesAfterToggle, rulesAfterShowingAll, rulesAfterShowingAt, rulesAfterReorder, rulesInEffect as rulesInEffectFor, type UnifiedRow } from './displayRuleEditing.ts';

/** The order a row being dragged would give, and the property of that row. */
export interface DraggedOrder {
	rules: DisplayRule[];
	property: string;
}

/** The properties a preview beside the list marks, which the list marks too. */
export interface PreviewMarks {
	hoveredProperty: string | null;
	selectedProperty: string | null;
}

const props = defineProps<{
	schemaProperties: PropertyDefinition[];
	displayRules: DisplayRule[];
	propertiesTargetingDependentSubjects: PropertyName[];
	/** Absent where the list has no preview beside it, as in the Layout creator. */
	preview?: PreviewMarks;
	/** The properties whose Display Attributes the list opens from their rows while they are shown. */
	propertiesWithDisplayAttributes?: string[];
	/** The properties whose Display Attributes have changes not yet saved, marked while they are shown. */
	unsavedProperties?: string[];
}>();

const emit = defineEmits<{
	'update:display-rules': [ rules: DisplayRule[] ];
	'hover': [ name: string | null ];
	'select': [ name: string ];
	'open': [ name: string ];
	/** Null once the drag is over. */
	'reordering': [ order: DraggedOrder | null ];
}>();

const listRef = ref<HTMLElement | null>( null );
const announcementRef = ref<LiveAnnouncementExposes | null>( null );

const componentRegistry = NeoWikiServices.getComponentRegistry();

const rulesInEffect = computed( (): DisplayRule[] =>
	rulesInEffectFor( props.schemaProperties, props.displayRules, props.propertiesTargetingDependentSubjects ) );

const rows = computed( () => unifiedRows( props.schemaProperties, rulesInEffect.value ) );
const propertyNamesLeftOut = computed( () => props.propertiesTargetingDependentSubjects.map( ( name ) => name.toString() ) );
const isDefault = computed( () => props.displayRules.length === 0 );
const shownRows = computed( () => rows.value.filter( ( row ) => row.shown ) );
const hiddenRows = computed( () => rows.value.filter( ( row ) => !row.shown ) );
const shownCount = computed( () => shownRows.value.length );

const groups = computed( () => {
	const shown = { label: 'neowiki-layout-editor-shown', shown: true, rows: shownRows.value };
	const hidden = { label: 'neowiki-layout-editor-hidden', shown: false, rows: hiddenRows.value };

	return hidden.rows.length > 0 ? [ shown, hidden ] : [ shown ];
} );

function keepShownList( element: Element | ComponentPublicInstance | null ): void {
	listRef.value = element instanceof HTMLElement ? element : null;
}

const hiddenListRef = ref<HTMLElement | null>( null );

function keepHiddenList( element: Element | ComponentPublicInstance | null ): void {
	hiddenListRef.value = element instanceof HTMLElement ? element : null;
}

// Toggling moves the row to the other group, as a new element: the focus follows it to its toggle there.
async function onToggle( name: string ): Promise<void> {
	const hadFocus = rowElement( name )?.contains( document.activeElement ) ?? false;

	emit( 'update:display-rules', rulesAfterToggle( props.schemaProperties, rulesInEffect.value, name ) );

	if ( hadFocus ) {
		await nextTick();
		rowElement( name )?.querySelector<HTMLElement>( '.ext-neowiki-display-rule-list__item__action' )?.focus();
	}
}

function onShowAll(): void {
	emit( 'update:display-rules', rulesAfterShowingAll( props.schemaProperties, rulesInEffect.value ) );
}

function toggleMessageKey( shown: boolean ): string {
	if ( lastShown( shown ) ) {
		return 'neowiki-layout-editor-keep-one-shown';
	}

	return shown ? 'neowiki-layout-editor-hide-property' : 'neowiki-layout-editor-show-property';
}

function lastShown( shown: boolean ): boolean {
	return shown && shownCount.value === 1;
}

const namesWithDisplayAttributes = computed( () => new Set( props.propertiesWithDisplayAttributes ) );

const unsavedNames = computed( () => new Set( props.unsavedProperties ) );

function opensDisplayAttributes( row: UnifiedRow ): boolean {
	return row.shown && namesWithDisplayAttributes.value.has( row.property.name.toString() );
}

function isUnsaved( row: UnifiedRow ): boolean {
	return row.shown && unsavedNames.value.has( row.property.name.toString() );
}

function onRowActivated( row: UnifiedRow ): void {
	const name = row.property.name.toString();

	if ( opensDisplayAttributes( row ) ) {
		emit( 'open', name );
	} else {
		emit( 'select', name );
	}
}

// Each group's selected row, or its first while it has none.
const tabStops = computed( () => new Set( groups.value.map( ( group ) => group.rows.find( isSelected ) ?? group.rows[ 0 ] ) ) );

function onRowKeydown( event: KeyboardEvent, groupRows: UnifiedRow[], index: number ): void {
	if ( event.target !== event.currentTarget ) {
		return;
	}

	const step = { ArrowDown: 1, ArrowUp: -1 }[ event.key ];

	if ( step !== undefined ) {
		event.preventDefault();

		if ( event.altKey ) {
			moveAndFocus( groupRows, index, step );
		} else {
			selectAndFocus( groupRows[ index + step ] );
		}
	} else if ( event.key === 'Enter' || event.key === ' ' ) {
		event.preventDefault();
		onRowActivated( groupRows[ index ] );
	}
}

function selectAndFocus( row: UnifiedRow | undefined ): void {
	if ( row === undefined ) {
		return;
	}

	const name = row.property.name.toString();
	emit( 'select', name );
	rowElement( name )?.focus();
}

function isHovered( row: UnifiedRow ): boolean {
	return row.property.name.toString() === props.preview?.hoveredProperty;
}

function isSelected( row: UnifiedRow ): boolean {
	return row.property.name.toString() === props.preview?.selectedProperty;
}

const listRoot = ref<HTMLElement | null>( null );

// Found when needed: a row moving to the other group is a new element there.
function rowElement( name: string ): HTMLElement | undefined {
	return [ ...listRoot.value?.querySelectorAll<HTMLElement>( '[data-mw-neowiki-property]' ) ?? [] ]
		.find( ( element ) => element.dataset.mwNeowikiProperty === name );
}

async function moveAndFocus( groupRows: UnifiedRow[], index: number, step: number ): Promise<void> {
	const row = groupRows[ index ];

	if ( !row.shown || groupRows[ index + step ] === undefined ) {
		return;
	}

	moveRow( index, index + step );
	await nextTick();
	rowElement( row.property.name.toString() )?.focus();
}

export interface DisplayRuleListExposes {
	focusProperty: ( name: string ) => void;
}

defineExpose( {
	focusProperty( name: string ): void {
		rowElement( name )?.focus();
	}
} );

// Shown rows come first, so a row's index is also its place among them.
function moveRow( from: number, to: number ): void {
	const announcement = movedAnnouncement( rows.value[ from ].property.name.toString(), to, shownCount.value );

	emit( 'update:display-rules', rulesAfterReorder( props.schemaProperties, rulesInEffect.value, from, to ) );
	announcementRef.value?.announce( announcement );
}

// Rows drag between the two groups of this list alone: to show a property where it is dropped, or to hide it.
const dragGroup = `ext-neowiki-display-rule-list-${ getCurrentInstance()!.uid }`;
const GHOST_CLASS = 'ext-neowiki-display-rule-list__item--ghost';

function nameOf( item: HTMLElement ): string {
	return item.dataset.mwNeowikiProperty!;
}

function reportOrder( rules: DisplayRule[], item: HTMLElement ): void {
	emit( 'reordering', { rules, property: nameOf( item ) } );
}

useSortable( listRef, {
	handle: REORDER_HANDLE_SELECTOR,
	group: { name: dragGroup, pull: true, put: true },
	ghostClass: GHOST_CLASS,
	onReorder: moveRow,
	onMove( oldIndex: number, newIndex: number ): void {
		emit( 'reordering', {
			rules: rulesAfterReorder( props.schemaProperties, rulesInEffect.value, oldIndex, newIndex ),
			property: rows.value[ oldIndex ].property.name.toString()
		} );
	},
	onMoveIn( item: HTMLElement, newIndex: number ): void {
		reportOrder( rulesAfterShowingAt( props.schemaProperties, rulesInEffect.value, nameOf( item ), newIndex ), item );
	},
	onDropIn( item: HTMLElement, _oldIndex: number | undefined, newIndex: number | undefined ): void {
		emit( 'update:display-rules', rulesAfterShowingAt( props.schemaProperties, rulesInEffect.value, nameOf( item ), newIndex! ) );
	},
	onDragEnd(): void {
		emit( 'reordering', null );
	}
} );

useSortable( hiddenListRef, {
	handle: '.ext-neowiki-display-rule-list__item__drag',
	// The last shown property stays shown, as its eye keeps it.
	group: { name: dragGroup, pull: true, put: (): boolean => shownCount.value > 1 },
	sort: false,
	ghostClass: GHOST_CLASS,
	onMoveIn( item: HTMLElement ): void {
		reportOrder( rulesAfterToggle( props.schemaProperties, rulesInEffect.value, nameOf( item ) ), item );
	},
	onDropIn( item: HTMLElement ): void {
		emit( 'update:display-rules', rulesAfterToggle( props.schemaProperties, rulesInEffect.value, nameOf( item ) ) );
	},
	onDragEnd(): void {
		emit( 'reordering', null );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-display-rule-list {
	&__header {
		display: flex;
		flex-wrap: wrap;
		align-items: center;
		gap: @spacing-50;
		/* In line with the rows' icons and actions, which start in the dialog's gutter. */
		padding: @spacing-25 @spacing-75 @spacing-25 @spacing-150;
		font-size: @font-size-small;
		color: @color-subtle;

		&__title {
			font-weight: @font-weight-bold;
		}

		&__note {
			flex: 1 1 @size-1600;
		}

		&__end {
			margin-inline-start: auto;
		}
	}

	&__group + &__group {
		margin-block-start: @spacing-75;
	}

	&__items {
		list-style: none;
		margin: 0;
		padding: 0;
	}

	&__item {
		user-select: none;

		&&--ghost {
			--ext-neowiki-editor-list-item-background: @background-color-interactive-subtle;
			opacity: @opacity-low;
		}

		&--hovered:not( .ext-neowiki-editor-list-item--selected ) {
			--ext-neowiki-editor-list-item-background: @background-color-interactive-subtle;
		}

		&--hidden &__name,
		&--hidden &__type {
			opacity: @opacity-medium;
		}

		&__actions {
			display: contents;
		}

		&__drag {
			display: inline-flex;
			align-items: center;
			justify-content: center;
			min-width: @min-size-interactive-pointer;
			min-height: @min-size-interactive-pointer;
			cursor: grab;
		}

		&__action-tooltip {
			display: inline-flex;

			// A disabled button receives no pointer events, so the browser never
			// surfaces its title tooltip on hover. Letting events fall through to
			// this wrapper makes its title the hover target, so the "at least one
			// must stay shown" explanation shows even while the toggle is disabled.
			.cdx-button:disabled {
				pointer-events: none;
			}
		}
	}
}
</style>
