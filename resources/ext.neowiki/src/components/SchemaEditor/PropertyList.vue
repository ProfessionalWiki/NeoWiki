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
			@click="emit( 'propertySelected', new PropertyName( '', true ) )"
		>
			<template #title>
				+ New property
			</template>
		</CdxCard>
	</div>
</template>

<script setup lang="ts">
import { CdxCard } from '@wikimedia/codex';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';
import { PropertyName } from '@neo/domain/PropertyDefinition.ts';

defineProps<{
	properties: PropertyDefinitionList;
}>();

const emit = defineEmits<{
	propertySelected: [ name: PropertyName ];
}>();
</script>
