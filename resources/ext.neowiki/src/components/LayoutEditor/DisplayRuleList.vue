<template>
	<div class="ext-neowiki-display-rule-list">
		<div class="ext-neowiki-display-rule-list__header">
			<span class="ext-neowiki-display-rule-list__header__count">
				{{ $i18n( 'neowiki-layout-editor-shown-count', shownCount, totalCount ).text() }}
			</span>
			<span
				v-if="isDefault"
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
			<CdxButton
				v-if="hasHidden"
				class="ext-neowiki-display-rule-list__reset"
				weight="quiet"
				@click="onShowAll"
			>
				{{ $i18n( 'neowiki-layout-editor-show-all-properties' ).text() }}
			</CdxButton>
		</div>
		<ul
			ref="listRef"
			class="ext-neowiki-display-rule-list__items"
		>
			<li
				v-for="( row, index ) in rows"
				:key="row.property.name.toString()"
				class="ext-neowiki-display-rule-list__item"
				:class="{
					'ext-neowiki-display-rule-list__item--hidden': !row.shown,
					'ext-neowiki-display-rule-list__item--hovered': isHovered( row ),
					'ext-neowiki-display-rule-list__item--selected': isSelected( row )
				}"
				@mouseenter="emit( 'hover', row.property.name.toString() )"
				@mouseleave="emit( 'hover', null )"
			>
				<ReorderHandle
					v-if="row.shown"
					:position="index"
					:count="shownCount"
					:label="reorderHandleLabel( row.property.name.toString() )"
					@move="moveRow( index, $event )"
				/>
				<span
					v-else
					class="ext-neowiki-display-rule-list__item__drag-placeholder"
				/>
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
				<button
					v-if="props.preview"
					:ref="( element ) => keepNameButton( row.property.name.toString(), element )"
					type="button"
					class="ext-neowiki-display-rule-list__item__name ext-neowiki-display-rule-list__item__name--selectable"
					:aria-pressed="isSelected( row )"
					@click="emit( 'select', row.property.name.toString() )"
				>
					{{ row.property.name.toString() }}
				</button>
				<span
					v-else
					class="ext-neowiki-display-rule-list__item__name"
				>
					{{ row.property.name.toString() }}
				</span>
			</li>
		</ul>
		<LiveAnnouncement ref="announcementRef" />
	</div>
</template>

<script setup lang="ts">
import { ref, computed, type ComponentPublicInstance } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconEye, cdxIconEyeClosed } from '@wikimedia/codex-icons';
import type { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import type { DisplayRule } from '@/domain/Layout.ts';
import { useSortable } from '@/composables/useSortable.ts';
import ReorderHandle from '@/components/common/ReorderHandle.vue';
import LiveAnnouncement, { type LiveAnnouncementExposes } from '@/components/common/LiveAnnouncement.vue';
import { movedAnnouncement, REORDER_HANDLE_SELECTOR, reorderHandleLabel } from '@/components/common/reordering.ts';
import { unifiedRows, rulesAfterToggle, rulesAfterShowingAll, rulesAfterReorder, type UnifiedRow } from './displayRuleEditing.ts';

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
}>();

const emit = defineEmits<{
	'update:display-rules': [ rules: DisplayRule[] ];
	'hover': [ name: string | null ];
	'select': [ name: string ];
	/** Null once the drag is over. */
	'reordering': [ order: DraggedOrder | null ];
}>();

const listRef = ref<HTMLElement | null>( null );
const announcementRef = ref<LiveAnnouncementExposes | null>( null );

// Without Display Rules, a View shows every property but those pointing at Dependent Subjects.
const rulesInEffect = computed( (): DisplayRule[] => {
	if ( props.displayRules.length > 0 ) {
		return props.displayRules;
	}

	const leftOut = props.propertiesTargetingDependentSubjects.map( ( name ) => name.toString() );

	return props.schemaProperties
		.filter( ( property ) => !leftOut.includes( property.name.toString() ) )
		.map( ( property ) => ( { property: property.name } ) );
} );

const rows = computed( () => unifiedRows( props.schemaProperties, rulesInEffect.value ) );
const propertyNamesLeftOut = computed( () => props.propertiesTargetingDependentSubjects.map( ( name ) => name.toString() ) );
const isDefault = computed( () => props.displayRules.length === 0 );
const shownCount = computed( () => rows.value.filter( ( row ) => row.shown ).length );
const totalCount = computed( () => props.schemaProperties.length );
const hasHidden = computed( () => shownCount.value < totalCount.value );

function onToggle( name: string ): void {
	emit( 'update:display-rules', rulesAfterToggle( props.schemaProperties, rulesInEffect.value, name ) );
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

function isHovered( row: UnifiedRow ): boolean {
	return row.property.name.toString() === props.preview?.hoveredProperty;
}

function isSelected( row: UnifiedRow ): boolean {
	return row.property.name.toString() === props.preview?.selectedProperty;
}

const nameButtons = new Map<string, HTMLElement>();

function keepNameButton( name: string, element: Element | ComponentPublicInstance | null ): void {
	if ( element instanceof HTMLElement ) {
		nameButtons.set( name, element );
	} else {
		nameButtons.delete( name );
	}
}

export interface DisplayRuleListExposes {
	focusProperty: ( name: string ) => void;
}

defineExpose( {
	focusProperty( name: string ): void {
		nameButtons.get( name )?.focus();
	}
} );

// Shown rows come first, so a row's index is also its place among them.
function moveRow( from: number, to: number ): void {
	const announcement = movedAnnouncement( rows.value[ from ].property.name.toString(), to, shownCount.value );

	emit( 'update:display-rules', rulesAfterReorder( props.schemaProperties, rulesInEffect.value, from, to ) );
	announcementRef.value?.announce( announcement );
}

useSortable( listRef, {
	handle: REORDER_HANDLE_SELECTOR,
	// Only shown rows take part in sorting. Hidden rows have no handle (can't be
	// grabbed) and excluding them here stops a shown row from being dropped into
	// the hidden region, where it would otherwise snap back on the next render.
	draggable: '.ext-neowiki-display-rule-list__item:not(.ext-neowiki-display-rule-list__item--hidden)',
	ghostClass: 'ext-neowiki-display-rule-list__item--ghost',
	onReorder: moveRow,
	onMove( oldIndex: number, newIndex: number ): void {
		emit( 'reordering', {
			rules: rulesAfterReorder( props.schemaProperties, rulesInEffect.value, oldIndex, newIndex ),
			property: rows.value[ oldIndex ].property.name.toString()
		} );
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
		padding: @spacing-25 @spacing-75;
		font-size: @font-size-small;
		color: @color-subtle;

		&__count {
			font-weight: @font-weight-bold;
		}

		&__note {
			flex: 1 1 @size-1600;
		}
	}

	&__reset {
		margin-inline-start: auto;
	}

	&__items {
		list-style: none;
		margin: 0;
		padding: 0;
	}

	&__item {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		padding: @spacing-50 @spacing-75;
		border-radius: @border-radius-base;
		user-select: none;

		&--ghost {
			opacity: @opacity-low;
			background-color: @background-color-interactive-subtle;
		}

		&:hover,
		&--hovered {
			background-color: @background-color-interactive-subtle;
		}

		&--selected,
		&--selected:hover {
			background-color: @background-color-progressive-subtle;
		}

		&--hidden &__name {
			opacity: @opacity-medium;
		}

		&__name {
			flex-grow: 1;
		}

		&__name--selectable {
			padding: 0;
			border: 0;
			border-radius: @border-radius-base;
			background: none;
			color: inherit;
			font: inherit;
			text-align: start;
			cursor: @cursor-base--hover;

			&:focus-visible {
				outline: @border-width-thick @border-style-base @border-color-progressive--focus;
			}
		}

		&--selected &__name {
			color: @color-progressive;
		}

		/* Holds a hidden row's name in line with the shown ones', which a handle precedes. */
		&__drag-placeholder {
			flex-shrink: 0;
			width: @min-size-interactive-pointer;
		}

		&__action-tooltip {
			display: inline-flex;
			flex-shrink: 0;

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
