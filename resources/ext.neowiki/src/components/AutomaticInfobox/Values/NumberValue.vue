<template>
	<div>
		{{ value.number }}
	</div>
</template>

<script setup lang="ts">
import { NumberValue } from '@neo/domain/Value.ts';
import { PropType } from 'vue';

defineProps( {
	value: {
		type: Object as PropType<NumberValue>,
		required: true
	}
} );
</script>
