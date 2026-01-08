<template>
	<div
		class="ext-neowiki-schema-editor"
		:class="{ 'ext-neowiki-schema-editor--has-selected-property': selectedProperty !== undefined }"
	>
		<PropertyList
			:properties="currentSchema.getPropertyDefinitions()"
			@property-selected="onPropertySelected"
			@property-created="onPropertyCreated"
		/>
		<PropertyDefinitionEditor
			v-if="selectedProperty !== undefined"
			:key="selectedPropertyName"
			:property="selectedProperty as PropertyDefinition"
			@update:property-definition="onPropertyUpdated"
		/>
	</div>
</template>

<script setup lang="ts">
import { PropertyDefinition, PropertyName } from '@/domain/PropertyDefinition';
import { Schema } from '@/domain/Schema.ts';
import { computed, ref } from 'vue';
import PropertyList from '@/components/SchemaEditor/PropertyList.vue';
import PropertyDefinitionEditor from '@/components/SchemaEditor/PropertyDefinitionEditor.vue';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';

const props = defineProps<{
	initialSchema: Schema;
}>();

const currentSchema = ref<Schema>( props.initialSchema );
const selectedPropertyName = ref<string | undefined>( undefined );

const selectedProperty = computed( () => {
	if ( selectedPropertyName.value === undefined ) {
		return undefined;
	}

	return currentSchema.value.getPropertyDefinitions().get(
		new PropertyName( selectedPropertyName.value )
	);
} );

function onPropertySelected( name: PropertyName ): void {
	selectedPropertyName.value = name.toString();
}

function onPropertyCreated( newProperty: PropertyDefinition ): void {
	currentSchema.value = currentSchema.value.withAddedPropertyDefinition( newProperty );
}

function onPropertyUpdated( updatedProperty: PropertyDefinition ): void {
	currentSchema.value = buildUpdatedSchema( updatedProperty );

	selectedPropertyName.value = updatedProperty.name.toString();
}

function propertyExists( name: string | undefined ): boolean {
	return name !== undefined &&
		currentSchema.value.getPropertyDefinitions().has( new PropertyName( name ) );
}

function buildUpdatedSchema( updatedProperty: PropertyDefinition ): Schema {
	if ( !propertyExists( selectedPropertyName.value ) ) {
		return currentSchema.value.withAddedPropertyDefinition( updatedProperty );
	}

	return new Schema(
		currentSchema.value.getName(),
		currentSchema.value.getDescription(),
		replacePropertyDefinition( updatedProperty )
	);
}

function replacePropertyDefinition( updatedProperty: PropertyDefinition ): PropertyDefinitionList {
	return new PropertyDefinitionList(
		Array.from( currentSchema.value.getPropertyDefinitions() ).map(
			function( property: PropertyDefinition ) {
				return property.name.toString() === selectedPropertyName.value ? updatedProperty : property;
			}
		)
	);
}

export interface SchemaEditorExposes {
	getSchema: () => Schema;
}

defineExpose( {
	getSchema: function(): Schema {
		return currentSchema.value as Schema;
	}
} );
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.ext-neowiki-schema-editor {
	display: grid;

	.ext-neowiki-schema-editor {
		&__property-list,
		&__property-editor {
			padding: $spacing-100;

			@media ( min-width: $min-width-breakpoint-desktop ) {
				padding: $spacing-150;
			}
		}

		&__property-list__menu {
			width: auto;

			@media ( min-width: $min-width-breakpoint-desktop ) {
				margin: (-$spacing-50) (-$spacing-75);

				.cdx-menu-item {
					border-top-right-radius: 0;
					border-bottom-right-radius: 0;
				}
			}
		}
	}

	.cdx-select-vue {
		display: block; /* Make the select element take the full width of the parent element */
	}

	&--has-selected-property {
		/*
			TODO: Temporary solution for responsive layout.
			Property list and editor should be in multiple steps for mobile.
		*/
		@media ( max-width: $max-width-breakpoint-tablet ) {
			.ext-neowiki-schema-editor {
				&__property-list {
					overflow-x: auto;
					padding: 0;
				}

				&__property-list__menu {
					.cdx-menu__listbox {
						display: flex;
						white-space: nowrap;
					}

					&.cdx-menu--has-footer .cdx-menu-item:last-of-type:not( :first-of-type ) {
						margin-top: 0;
					}

					.cdx-menu-item {
						border-radius: 0;
					}
				}

				&__property-editor {
					border-block-start: $border-subtle;
				}
			}
		}

		@media ( min-width: $min-width-breakpoint-desktop ) {
			grid-template-columns: minmax( 0, 20rem ) auto;

			.ext-neowiki-schema-editor {
				&__property-list__menu {
					margin-inline-end: -$spacing-150;
				}

				&__property-editor {
					border-inline-start: $border-subtle;
				}
			}
		}
	}
}
</style>
