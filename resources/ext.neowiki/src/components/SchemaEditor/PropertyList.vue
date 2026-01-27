<template>
	<div class="ext-neowiki-schema-editor__property-list">
		<CdxMenu
			v-model:selected="selectedValue"
			:expanded="true"
			class="ext-neowiki-schema-editor__property-list__menu"
			:menu-items="menuItems"
			:footer="menuFooter"
			@update:selected="onMenuSelect"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { CdxMenu, MenuItemData } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
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
	}
}
</style>
