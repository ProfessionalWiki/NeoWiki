<template>
	<div class="ext-neowiki-schema-editor__property-list">
		<CdxMenu
			v-model:selected="selectedValue"
			:expanded="true"
			class="ext-neowiki-schema-editor__property-list__menu"
			:menu-items="menuItems"
			:footer="menuFooter"
			@update:selected="onMenuSelect"
		>
			<template #default="{ menuItem }">
				<span class="ext-neowiki-schema-editor__property-list__menu-item__content cdx-menu-item__content">
					<CdxIcon
						v-if="menuItem.icon"
						class="cdx-menu-item__icon"
						:icon="menuItem.icon"
					/>
					<span class="cdx-menu-item__text">
						<span class="cdx-menu-item__text__label">
							{{ menuItem.label }}
						</span>
						<span
							v-if="menuItem.description"
							class="cdx-menu-item__text__description"
						>
							{{ menuItem.description }}
						</span>
					</span>
					<CdxButton
						v-if="menuItem.value !== 'new-property'"
						class="ext-neowiki-schema-editor__property-list__menu-item__delete"
						:aria-label="$i18n( 'neowiki-schema-editor-delete-property' ).text()"
						weight="quiet"
						action="destructive"
						@click.stop="onDeleteProperty( menuItem.value )"
					>
						<CdxIcon :icon="cdxIconTrash" />
					</CdxButton>
				</span>
			</template>
		</CdxMenu>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { CdxMenu, MenuItemData, CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconTrash } from '@wikimedia/codex-icons';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = defineProps<{
	properties: PropertyDefinitionList;
	selectedPropertyName?: string;
}>();

const emit = defineEmits<{
	propertySelected: [ name: PropertyName ];
	propertyCreated: [ property: PropertyDefinition ];
	propertyDeleted: [ name: PropertyName ];
}>();

const componentRegistry = NeoWikiServices.getComponentRegistry();

// CdxMenu doesn't support passing a PropertyName as a value, so we use a string instead.
const selectedValue = ref( props.selectedPropertyName ?? '' );

watch( () => props.selectedPropertyName, ( newProperty ) => {
	if ( newProperty !== undefined ) {
		selectedValue.value = newProperty;
	}
} );

const menuItems = computed( (): MenuItemData[] => [ ...props.properties ].map( ( property: PropertyDefinition ): MenuItemData => ( {
	label: property.name.toString(),
	value: property.name.toString(),
	description: getMenuDescription( property ),
	icon: componentRegistry.getIcon( property.type )
} ) ) );

const menuFooter: MenuItemData = {
	label: mw.msg( 'neowiki-schema-editor-new-property' ),
	value: 'new-property',
	icon: cdxIconAdd
};

function getMenuDescription( property: PropertyDefinition ): string {
	const typeLabel = mw.msg( componentRegistry.getLabel( property.type ) );
	return property.required ? typeLabel : `${ typeLabel }・${ mw.msg( 'neowiki-schema-editor-optional' ) }`;
}

function onDeleteProperty( propertyName: string ): void {
	emit( 'propertyDeleted', new PropertyName( propertyName ) );
}

function onMenuSelect( payload: string ): void {
	if ( payload === menuFooter.value ) {
		addNewProperty();
	} else {
		emit( 'propertySelected', new PropertyName( payload ) );
	}
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
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-editor__property-list {
	&__menu {
		&.cdx-menu {
			position: relative;
			border: 0;
			box-shadow: none;

			// HACK: The menu footer is sticky on the initial state for unknown reasons.
			// It behaves correctly after a new property is created, so we use this
			// workaround to make it behave as expected.
			&--has-footer {
				.cdx-menu-item:last-of-type {
					position: relative;

					&:not( :first-of-type ) {
						border-top: 0;
						margin-top: @spacing-100;
					}
				}

				.cdx-menu__listbox {
					margin-bottom: 0 !important;
				}
			}

			.cdx-menu-item {
				border-radius: @border-radius-base;
			}
		}

		.cdx-menu-item__icon.cdx-icon {
			background-position: @background-position-base;
			background-repeat: no-repeat;
			background-size: @background-size-search-figure;
			background-color: @background-color-interactive-subtle;
			// Thumbnail should never shrink when it's in a flex layout with other elements.
			flex-shrink: 0;
			box-sizing: @box-sizing-base;
			// Values of thumbnail as declared within the MenuItem component, f.e. in TypeaheadSearch.
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

		.cdx-menu-item:hover,
		.cdx-menu-item--selected,
		.cdx-menu-item:focus-within {
			.ext-neowiki-schema-editor__property-list__menu-item__delete {
				opacity: 1;
			}
		}
	}

	&__menu-item {
		&__content {
			// Needed the specificity to override the Codex style.
			&.cdx-menu-item__content.cdx-menu-item__content {
				gap: @spacing-50;
				align-items: center;
			}

			.cdx-menu-item__text {
				flex-grow: 1;
			}
		}

		&__delete {
			opacity: 0;

			.cdx-menu-item:hover &,
			.cdx-menu-item--selected &,
			.cdx-menu-item:focus-within & {
				opacity: 1;
			}
		}
	}
}
</style>
