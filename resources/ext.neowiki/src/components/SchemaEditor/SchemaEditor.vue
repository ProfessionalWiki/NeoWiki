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

const props = defineProps<{
	schema: Schema;
}>();

let properties = props.schema.getPropertyDefinitions(); // TODO: should probably be reactive

function selectProperty( name: PropertyName ): void {
	properties = properties.withPropertyDefinition(
		{ // TODO: construct the correct property type class
			name: new PropertyName( properties.get( name ).name + 'x' ), // TODO: random junk to trigger a change
			type: properties.get( name ).type,
			description: properties.get( name ).description,
			required: properties.get( name ).required,
			default: properties.get( name ).default
		} as PropertyDefinition
	);

	emitUpdatedSchema();
}

function getSchema(): Schema {
	return new Schema(
		props.schema.getName(),
		props.schema.getDescription(), // TODO: make editable via UI
		properties
	);
}

const emit = defineEmits<{
	'update:schema': [ Schema ];
}>();

function emitUpdatedSchema(): void {
	emit( 'update:schema', getSchema() );
}

</script>

<style scoped>
.ext-neowiki-schema-editor {
	display: flex;
}
</style>
