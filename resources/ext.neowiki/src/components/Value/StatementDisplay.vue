<template>
	<component
		:is="component"
		:value="value"
		:property="property"
	/>
</template>

<script setup lang="ts">
import { Component, computed } from 'vue';
import type { Value } from '@/domain/Value';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = defineProps<{
	value: Value;
	property: PropertyDefinition;
}>();

const component = computed<Component>( () => NeoWikiServices.getComponentRegistry().getValueDisplayComponent( props.property.type ) );
</script>
