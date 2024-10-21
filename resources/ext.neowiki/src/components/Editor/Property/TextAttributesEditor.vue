<template>
	<div />
</template>

<script setup lang="ts">
import { PropType } from 'vue';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';

defineProps( {
	// eslint-disable-next-line vue/no-unused-properties
	property: {
		type: Object as PropType<PropertyDefinition>,
		required: true
	}
} );
</script>
