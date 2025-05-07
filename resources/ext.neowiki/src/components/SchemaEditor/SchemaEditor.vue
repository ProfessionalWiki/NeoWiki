<template>
	<div class="ext-neowiki-schema-editor">
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
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition';
import { Schema } from '@neo/domain/Schema.ts';
import { computed, ref } from 'vue';
import PropertyList from '@/components/SchemaEditor/PropertyList.vue';
import PropertyDefinitionEditor from '@/components/SchemaEditor/PropertyDefinitionEditor.vue';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';

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
	currentSchema.value = buildUpdatedSchema( newProperty );
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

<style scoped>
.ext-neowiki-schema-editor {
	display: flex;
}
</style>
