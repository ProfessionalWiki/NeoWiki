<template>
	<div>
		TODO: {{ ( value as RelationValue ).targetIds.join( ', ' ) }}
	</div>
</template>

<script setup lang="ts">
import { PropType } from 'vue';
import { RelationValue, Value } from '@neo/domain/Value.ts';

defineProps( {
	value: {
		type: Object as PropType<Value>,
		required: true
	}
} );
</script>
