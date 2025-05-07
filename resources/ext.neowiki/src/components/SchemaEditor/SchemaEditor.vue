<template>
	<div class="ext-neowiki-schema-editor">
		<PropertyList
			:properties="properties"
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
	schema: Schema;
}>();

const properties = computed( () => props.schema.getPropertyDefinitions() );
const selectedProperty = ref<PropertyDefinition | undefined>( undefined );
let selectedPropertyName: string | undefined;

function onPropertySelected( name: PropertyName ): void {
	selectedPropertyName = name.toString();
	selectedProperty.value = properties.value.get( name );
}

function onPropertyCreated( newProperty: PropertyDefinition ): void {
	doStuffWithUpdatedSchema( props.schema.withAddedPropertyDefinition( newProperty ) );
}

function onPropertyUpdated( updatedProperty: PropertyDefinition ): void {
	const updatedSchema = buildUpdatedSchema( updatedProperty );

	selectedPropertyName = updatedProperty.name.toString();
	selectedProperty.value = updatedProperty;

	doStuffWithUpdatedSchema( updatedSchema );
}

function doStuffWithUpdatedSchema( schema: Schema ): void {
	// This function is a placeholder
	// TODO: keep track of the Schema and only emit the update event when appropriate (maybe no event and just pull on save)
	emit( 'update:schema', schema );
}

function buildUpdatedSchema( updatedProperty: PropertyDefinition ): Schema {
	if ( selectedPropertyName === undefined || !properties.value.has( new PropertyName( selectedPropertyName ) ) ) {
		return props.schema.withAddedPropertyDefinition( updatedProperty );
	}

	const updatedProperties = Array.from( properties.value ).map(
		function( prop: PropertyDefinition ) {
			return prop.name.toString() === selectedPropertyName ? updatedProperty : prop;
		}
	);

	return new Schema(
		props.schema.getName(),
		props.schema.getDescription(),
		new PropertyDefinitionList( updatedProperties )
	);
}

const emit = defineEmits<{
	'update:schema': [ Schema ];
}>();

</script>

<style scoped>
.ext-neowiki-schema-editor {
	display: flex;
}
</style>
