<template>
	<div>
		{{ value }}
	</div>
</template>

<script setup lang="ts">
import { Value, ValueType } from '@neo/domain/Value.ts';
import { computed, PropType } from 'vue';
import { NumberProperty } from '@neo/domain/valueFormats/Number.ts';

const props = defineProps( {
	value: {
		type: Object as PropType<Value>,
		required: true
	},
	property: {
		type: Object as PropType<NumberProperty>,
		required: true
	}
} );

const value = computed( (): string => {
	if ( props.value.type !== ValueType.Number ) {
		return '';
	}

	if ( props.property.precision !== undefined ) {
		return props.value.number.toFixed( props.property.precision );
	}

	return props.value.number.toString();
} );
</script>
