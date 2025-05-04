<template>
	<div class="ext-neowiki-schema-editor">
		<PropertyList
			:properties="properties"
			@property-selected="selectProperty"
		/>
		<PropertyDefinitionEditor
			v-if="selectedProperty !== undefined"
			:property="selectedProperty"
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

const props = defineProps<{
	schema: Schema;
}>();

const properties = computed( () => props.schema.getPropertyDefinitions() );
const selectedProperty = ref<PropertyDefinition>();

function selectProperty( name: PropertyName ): void {
	console.log('selectedProperty', name.toString());
	if ( name.toString() !== '' ) {
		selectedProperty.value = properties.value.get( name );
	} else {
		selectedProperty.value = {
			name: new PropertyName( 'New Property ' + Object.keys( props.schema.getPropertyDefinitions().asRecord() ).length, true ),
			type: 'text',
			description: '',
			required: false,
			default: undefined
		} as PropertyDefinition;
	}
}

function onPropertyUpdated( property: PropertyDefinition ): void {
	// TODO: replace the property in props.schema?
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
