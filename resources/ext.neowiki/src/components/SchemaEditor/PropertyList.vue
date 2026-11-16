<template>
	<div class="ext-neowiki-schema-editor__property-list">
		<ul
			ref="listRef"
			role="listbox"
			class="ext-neowiki-property-list"
			@keydown="onKeydown"
		>
			<li
				role="option"
				class="ext-neowiki-property-list__item ext-neowiki-property-list__item--schema"
				v-bind="rowState( SCHEMA_POSITION )"
				@click="selectAndFocus( SCHEMA_POSITION )"
			>
				<CdxIcon
					class="ext-neowiki-property-list__item__icon"
					:icon="cdxIconSettings"
				/>
				<span class="ext-neowiki-property-list__item__text">
					<span class="ext-neowiki-property-list__item__text__label">
						{{ $i18n( 'neowiki-schema-editor-schema' ).text() }}
					</span>
					<span
						v-if="schemaError"
						class="ext-neowiki-property-list__item__text__error"
					>
						<CdxIcon
							:icon="cdxIconError"
							size="small"
						/>
						{{ schemaError }}
					</span>
				</span>
			</li>
			<li
				v-if="props.showsLabelRow"
				role="option"
				class="ext-neowiki-property-list__item ext-neowiki-property-list__item--subject-label"
				v-bind="rowState( LABEL_POSITION )"
				@click="selectAndFocus( LABEL_POSITION )"
			>
				<CdxIcon
					class="ext-neowiki-property-list__item__icon"
					:icon="cdxIconTag"
				/>
				<span class="ext-neowiki-property-list__item__text">
					<span class="ext-neowiki-property-list__item__text__label">
						{{ $i18n( 'neowiki-schema-editor-subject-label' ).text() }}
					</span>
					<span class="ext-neowiki-property-list__item__text__description">
						{{ describeType( TextType.typeName, false ) }}
					</span>
				</span>
			</li>
			<li
				v-for="( property, index ) in propertyArray"
				:key="property.name.toString()"
				role="option"
				class="ext-neowiki-property-list__item ext-neowiki-property-list__item--property"
				v-bind="rowState( propertyPosition( index ) )"
				@click="selectAndFocus( propertyPosition( index ) )"
			>
				<CdxIcon
					v-if="getPropertyIcon( property )"
					class="ext-neowiki-property-list__item__icon"
					:icon="getPropertyIcon( property )!"
				/>
				<span class="ext-neowiki-property-list__item__text">
					<span class="ext-neowiki-property-list__item__text__label">
						{{ property.name.toString() }}
					</span>
					<span
						v-if="describeType( property.type, property.required )"
						class="ext-neowiki-property-list__item__text__description"
					>
						{{ describeType( property.type, property.required ) }}
					</span>
				</span>
				<span class="ext-neowiki-property-list__item__actions">
					<CdxButton
						class="ext-neowiki-property-list__item__actions__delete"
						:aria-label="$i18n( 'neowiki-schema-editor-delete-property' ).text()"
						weight="quiet"
						action="destructive"
						@click.stop="emit( 'propertyDeleted', property.name )"
					>
						<CdxIcon :icon="cdxIconTrash" />
					</CdxButton>
					<span class="ext-neowiki-property-list__item__actions__drag-handle">
						<CdxIcon
							:icon="cdxIconDraggable"
							:aria-hidden="true"
						/>
					</span>
				</span>
			</li>
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
			<span class="ext-neowiki-property-list__item__text">
				<span class="ext-neowiki-property-list__item__text__label">
					{{ $i18n( 'neowiki-schema-editor-new-property' ).text() }}
				</span>
			</span>
		</button>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, nextTick } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconDraggable, cdxIconError, cdxIconSettings, cdxIconTag, cdxIconTrash } from '@wikimedia/codex-icons';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import { TextType } from '@/domain/propertyTypes/Text.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useSortable } from '@/composables/useSortable.ts';
import type { Icon } from '@wikimedia/codex-icons';
import { LABEL_ROW, propertyRow, SCHEMA_ROW, type PropertyListRow } from '@/components/SchemaEditor/PropertyListRow.ts';

const props = defineProps<{
	properties: PropertyDefinitionList;
	selection: PropertyListRow;
	showsLabelRow: boolean;
	/** A problem with the Schema's own fields, shown on its row too: they are out of sight while another row is selected. */
	schemaError?: string;
}>();

const emit = defineEmits<{
	select: [ row: PropertyListRow ];
	addProperty: [];
	propertyDeleted: [ name: PropertyName ];
	propertyReordered: [ names: PropertyName[] ];
}>();

const componentRegistry = NeoWikiServices.getComponentRegistry();

const listRef = ref<HTMLElement | null>( null );

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
function rowState( position: number ): { class: Record<string, boolean>; 'aria-selected': boolean; tabindex: number } {
	const selected = position === selectedPosition.value;

	return {
		class: { 'ext-neowiki-property-list__item--selected': selected },
		'aria-selected': selected,
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
	listRef.value?.querySelectorAll<HTMLElement>( '[role="option"]' )[ selectedPosition.value ]?.focus();
}

function moveProperty( fromIndex: number, toIndex: number ): void {
	const names = propertyArray.value.map( ( p ) => p.name );
	const [ moved ] = names.splice( fromIndex, 1 );
	names.splice( toIndex, 0, moved );
	emit( 'propertyReordered', names );
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

.ext-neowiki-property-list {
	list-style: none;
	margin: 0;
	padding: 0;

	&__item {
		display: flex;
		align-items: center;
		gap: @spacing-50;
		padding: @spacing-50 @spacing-75;
		border-radius: @border-radius-base;
		cursor: grab;
		user-select: none;
		position: relative;
		overflow: hidden;

		&:hover {
			background-color: @background-color-interactive-subtle;
		}

		&--selected {
			background-color: @background-color-progressive-subtle;
		}

		&--schema,
		&--subject-label {
			cursor: pointer;
		}

		/* Set apart from the rows of what the Schema's Subjects hold by a rule in the gap below it, inset as the rows'
			content is, and in a grey lighter than lines that divide the dialog. Drawn outside the row, which has no
			actions to clip. */
		&--schema {
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

		&--ghost {
			opacity: 0.5;
			background-color: @background-color-interactive-subtle;
		}

		&__icon.cdx-icon {
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

		&__text {
			flex-grow: 1;
			min-width: 0;

			&__label {
				display: block;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			&__description {
				display: block;
				font-size: @font-size-small;
				color: @color-subtle;
				overflow: hidden;
				text-overflow: ellipsis;
				white-space: nowrap;
			}

			/* As a field's validation message, and wrapped rather than cut short: it says what to fix. */
			&__error {
				display: flex;
				align-items: flex-start;
				gap: @spacing-25;
				font-size: @font-size-small;
				color: @color-error;

				.cdx-icon {
					flex-shrink: 0;
					color: @color-error;
				}
			}
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
			background: linear-gradient( to right, transparent, @background-color-base 40% );
			opacity: 0;
			transform: translateX( @spacing-75 );
			transition: opacity @transition-duration-medium @transition-timing-function-system, transform @transition-duration-medium @transition-timing-function-system;

			.ext-neowiki-property-list__item:hover &,
			.ext-neowiki-property-list__item--selected &,
			.ext-neowiki-property-list__item:has( :focus-visible ) & {
				// :has( :focus-visible ) rather than :focus-within: keyboard tabbing must reveal the
				// action controls, but a mouse click also focuses the button it hits and would keep the
				// cluster pinned visible after the pointer leaves.
				opacity: 1;
				transform: translateX( 0 );
			}

			.ext-neowiki-property-list__item:hover & {
				background: linear-gradient( to right, transparent, @background-color-interactive-subtle 40% );
			}

			.ext-neowiki-property-list__item--selected & {
				background: linear-gradient( to right, transparent, @background-color-progressive-subtle 40% );
			}

			&__drag-handle {
				min-width: @min-size-interactive-pointer;
				min-height: @min-size-interactive-pointer;
				padding-inline: @spacing-30; /* Replicate CdxButton padding */
				display: inline-flex;
				align-items: center;
				justify-content: center;
				box-sizing: border-box;

				.cdx-icon {
					color: @color-placeholder;
				}
			}
		}
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
	}

	/* Inside the row: the pane around the list clips at the dialog's edge. */
	&__item:focus-visible,
	&__add-item:focus-visible {
		outline: @border-width-thick solid @outline-color-progressive--focus;
		outline-offset: -@border-width-thick;
	}
}
</style>
