<template>
	<div class="ext-neowiki-schema-editor">
		<div class="ext-neowiki-schema-editor__schema">
			Schema: {{ schema.getName() }}

			<div class="ext-neowiki-schema-editor__properties__list">
				<CdxCard
					v-for="( property ) in properties"
					:key="property.name.toString()"
					url="#"
					@click="selectProperty( property.name )"
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
import { Schema } from '@neo/domain/Schema.ts';
import { computed } from 'vue';

const props = defineProps<{
	schema: Schema;
}>();

const properties = computed( () => props.schema.getPropertyDefinitions() );

function getPropertyDefinitionWithAdjustedName( name: PropertyName ): PropertyDefinition {
	const property = properties.value.get( name );

	return { // TODO: construct the correct property type class
		name: new PropertyName( property.name + 'x' ), // TODO: random junk to trigger a change
		type: property.type,
		description: property.description,
		required: property.required,
		default: property.default
	} as PropertyDefinition;
}

function selectProperty( name: PropertyName ): void {
	emitUpdatedSchema(
		new Schema(
			props.schema.getName(),
			props.schema.getDescription(), // TODO: make editable via UI
			properties.value.withPropertyDefinition( getPropertyDefinitionWithAdjustedName( name ) )
		)
	);
}

const emit = defineEmits<{
	'update:schema': [ Schema ];
}>();

function emitUpdatedSchema( schema: Schema ): void {
	emit( 'update:schema', schema );
}

</script>

<style scoped>
.ext-neowiki-schema-editor {
	display: flex;
}
</style>
