<template>
	<div class="ext-neowiki-schema-editor__property-list">
		<ul
			ref="listRef"
			role="listbox"
			class="ext-neowiki-property-list"
			@keydown="onKeydown"
		>
			<li
				v-for="property in propertyArray"
				:key="property.name.toString()"
				role="option"
				class="ext-neowiki-property-list__item"
				:class="{ 'ext-neowiki-property-list__item--selected': property.name.toString() === selectedValue }"
				:aria-selected="property.name.toString() === selectedValue"
				:tabindex="property.name.toString() === selectedValue ? 0 : -1"
				@click="onItemClick( property.name.toString() )"
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
						v-if="getPropertyDescription( property )"
						class="ext-neowiki-property-list__item__text__description"
					>
						{{ getPropertyDescription( property ) }}
					</span>
				</span>
				<span class="ext-neowiki-property-list__item__actions">
					<CdxButton
						class="ext-neowiki-property-list__item__actions__delete"
						:aria-label="$i18n( 'neowiki-schema-editor-delete-property' ).text()"
						weight="quiet"
						action="destructive"
						@click.stop="onDeleteProperty( property.name.toString() )"
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
			@click="addNewProperty"
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
import { ref, computed, watch } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconDraggable, cdxIconTrash } from '@wikimedia/codex-icons';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useSortable } from '@/composables/useSortable.ts';
import type { Icon } from '@wikimedia/codex-icons';

const props = defineProps<{
	properties: PropertyDefinitionList;
	selectedPropertyName?: string;
}>();

const emit = defineEmits<{
	propertySelected: [ name: PropertyName ];
	propertyCreated: [ property: PropertyDefinition ];
	propertyDeleted: [ name: PropertyName ];
	propertyReordered: [ names: PropertyName[] ];
}>();

const componentRegistry = NeoWikiServices.getComponentRegistry();

const selectedValue = ref( props.selectedPropertyName ?? '' );
const listRef = ref<HTMLElement | null>( null );

watch( () => props.selectedPropertyName, ( newProperty ) => {
	if ( newProperty !== undefined ) {
		selectedValue.value = newProperty;
	}
} );

const propertyArray = computed( (): PropertyDefinition[] => [ ...props.properties ] );

function getPropertyIcon( property: PropertyDefinition ): Icon | undefined {
	return componentRegistry.getIcon( property.type );
}

function getPropertyDescription( property: PropertyDefinition ): string {
	const typeLabel = mw.msg( componentRegistry.getLabel( property.type ) );
	return property.required ? typeLabel : `${ typeLabel }・${ mw.msg( 'neowiki-schema-editor-optional' ) }`;
}

function onItemClick( propertyName: string ): void {
	selectedValue.value = propertyName;
	emit( 'propertySelected', new PropertyName( propertyName ) );
}

function onDeleteProperty( propertyName: string ): void {
	emit( 'propertyDeleted', new PropertyName( propertyName ) );
}

function addNewProperty(): void {
	const newProperty = createNewProperty();
	selectedValue.value = newProperty.name.toString();
	emit( 'propertyCreated', newProperty );
	emit( 'propertySelected', newProperty.name );
}

function createNewProperty(): PropertyDefinition {
	return {
		name: generateUniquePropertyName(),
		type: 'text',
		description: '',
		required: false,
		default: undefined
	} as PropertyDefinition;
}

function generateUniquePropertyName(): PropertyName {
	const existingProps = Object.keys( props.properties.asRecord() );
	let counter = 1;
	let name = `New Property ${ counter }`;

	while ( existingProps.includes( name ) ) {
		counter++;
		name = `New Property ${ counter }`;
	}

	return new PropertyName( name );
}

function getSelectedIndex(): number {
	return propertyArray.value.findIndex( ( p ) => p.name.toString() === selectedValue.value );
}

function focusItem( index: number ): void {
	const items = listRef.value?.querySelectorAll<HTMLElement>( '[role="option"]' );
	items?.[ index ]?.focus();
}

function selectAndFocus( index: number ): void {
	const property = propertyArray.value[ index ];
	if ( property ) {
		onItemClick( property.name.toString() );
		focusItem( index );
	}
}

function moveProperty( fromIndex: number, toIndex: number ): void {
	const names = propertyArray.value.map( ( p ) => p.name );
	const [ moved ] = names.splice( fromIndex, 1 );
	names.splice( toIndex, 0, moved );
	emit( 'propertyReordered', names );
}

function onKeydown( event: KeyboardEvent ): void {
	const currentIndex = getSelectedIndex();
	if ( currentIndex === -1 ) {
		return;
	}

	const lastIndex = propertyArray.value.length - 1;

	if ( event.key === 'ArrowDown' ) {
		event.preventDefault();
		if ( event.altKey && currentIndex < lastIndex ) {
			moveProperty( currentIndex, currentIndex + 1 );
			selectAndFocus( currentIndex + 1 );
		} else if ( !event.altKey && currentIndex < lastIndex ) {
			selectAndFocus( currentIndex + 1 );
		}
	} else if ( event.key === 'ArrowUp' ) {
		event.preventDefault();
		if ( event.altKey && currentIndex > 0 ) {
			moveProperty( currentIndex, currentIndex - 1 );
			selectAndFocus( currentIndex - 1 );
		} else if ( !event.altKey && currentIndex > 0 ) {
			selectAndFocus( currentIndex - 1 );
		}
	}
}

useSortable( listRef, {
	onReorder( oldIndex: number, newIndex: number ): void {
		moveProperty( oldIndex, newIndex );
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
			.ext-neowiki-property-list__item:focus-within & {
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
}
</style>
