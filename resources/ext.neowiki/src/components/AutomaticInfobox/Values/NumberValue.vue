<template>
	<div>
		{{ getNumber() }}
	</div>
</template>

<script setup lang="ts">
import { NumberValue } from '@neo/domain/Value.ts';
import { PropType } from 'vue';
import { NumberProperty } from '@neo/domain/valueFormats/Number.ts';

const props = defineProps( {
	value: {
		type: Object as PropType<NumberValue>,
		required: true
	},
	property: {
		type: Object as PropType<NumberProperty>,
		required: true
	}
} );

const getNumber = (): string => {
	// TODO: handle non-NumberValue

	if ( props.property.precision !== undefined ) {
		return props.value.number.toFixed( props.property.precision );
	}

	return props.value.number.toString();
};
</script>
