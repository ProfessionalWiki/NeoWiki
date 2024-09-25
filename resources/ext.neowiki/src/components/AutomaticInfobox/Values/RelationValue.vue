<template>
	<div>
		TODO: {{ value.targetIds.join( ', ' ) }}
	</div>
</template>

<script setup lang="ts">
import { PropType } from 'vue';
import { RelationValue } from '@neo/domain/Value.ts';

defineProps( {
	value: {
		type: Object as PropType<RelationValue>,
		required: true
	}
} );
</script>
