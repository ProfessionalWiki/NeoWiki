<template>
	<div class="ext-neowiki-schema-editor">
		<div class="ext-neowiki-schema-editor__schema">
			Schema: {{ schemaName }}

			<div class="ext-neowiki-schema-editor__properties__list">
				<CdxCard
					v-for="( property, index ) in localProperties"
					:key="property.name.toString()"
					url="#"
					@click="selectProperty( index )"
				>
					<template #title>
						{{ property.name.toString() }}
					</template>
					<template #description>
						{{ property.type }}
					</template>
				</CdxCard>
			</div>
		</div>

		<div class="ext-neowiki-schema-editor__property-editor">
			PropertyDefinitionEditor
		</div>
	</div>
</template>

<script setup lang="ts">
import { CdxCard } from '@wikimedia/codex';
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition';
import { SchemaEditorData } from '@/components/SchemaEditor/SchemaEditorDialog.vue';

const props = defineProps<{
	schemaName: string;
	description: string;
	properties: PropertyDefinition[];
}>();

const emit = defineEmits<{
	'update:schema': [ SchemaEditorData ];
}>();

const localProperties = props.properties;

const selectProperty = ( index: number ): void => {
	// TODO: construct the correct property type class
	localProperties[ index ] = {
		name: new PropertyName( localProperties[ index ].name + 'x' ), // TODO: random junk to trigger a change
		type: localProperties[ index ].type,
		description: localProperties[ index ].description,
		required: localProperties[ index ].required,
		default: localProperties[ index ].default
	} as PropertyDefinition;

	emitUpdatedSchema();
};

const emitUpdatedSchema = (): void => {
	emit( 'update:schema', {
		description: props.description,
		properties: localProperties
	} );
};

</script>

<style scoped>
.ext-neowiki-schema-editor {
	display: flex;
}
</style>
