<template>
	<div>
		{{ value }}
	</div>
</template>

<script setup lang="ts">
import { ValueType } from '@/domain/Value.ts';
import { computed } from 'vue';
import { NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<NumberProperty>>();

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
