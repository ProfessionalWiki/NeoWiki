<template>
	<div>
		{{ displayText }}
	</div>
</template>

<script setup lang="ts">
import { ValueType } from '@/domain/Value.ts';
import { computed } from 'vue';
import { SelectProperty } from '@/domain/propertyTypes/Select.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<SelectProperty>>();

const displayText = computed( () => {
	if ( props.value.type !== ValueType.String ) {
		return '';
	}
	return props.value.parts.filter( ( part ) => part.trim() !== '' ).join( ', ' );
} );
</script>
