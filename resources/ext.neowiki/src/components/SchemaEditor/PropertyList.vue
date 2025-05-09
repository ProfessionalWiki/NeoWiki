<template>
	<div class="ext-neowiki-schema-editor__property-list">
		<CdxCard
			v-for="( property ) in properties"
			:key="property.name.toString()"
			url="#"
			@click="emit( 'propertySelected', property.name )"
		>
			<template #title>
				{{ property.name.toString() }}
			</template>
			<template #description>
				{{ property.type }}
			</template>
		</CdxCard>

		<CdxCard
			url="#"
			@click="addNewProperty"
		>
			<template #title>
				{{ $i18n( 'neowiki-schema-editor-new-property' ).text() }}
			</template>
		</CdxCard>
	</div>
</template>

<script setup lang="ts">
import { CdxCard } from '@wikimedia/codex';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';
import { PropertyDefinition, PropertyName } from '@neo/domain/PropertyDefinition.ts';

const props = defineProps<{
	properties: PropertyDefinitionList;
}>();

const emit = defineEmits<{
	propertySelected: [ name: PropertyName ];
	propertyCreated: [ property: PropertyDefinition ];
}>();

function addNewProperty(): void {
	const newProperty = createNewProperty();
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
