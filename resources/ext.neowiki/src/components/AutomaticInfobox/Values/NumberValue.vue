<template>
	<div>
		{{ ( value as NumberValue ).number }}
	</div>
</template>

<script setup lang="ts">
import { NumberValue, Value } from '@neo/domain/Value.ts';
import { PropType } from 'vue';

defineProps( {
	value: {
		type: Object as PropType<Value>,
		required: true
	}
} );
</script>
