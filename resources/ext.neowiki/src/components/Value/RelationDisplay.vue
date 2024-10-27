<template>
	<div>
		{{ todo }}
	</div>
</template>

<script setup lang="ts">
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { RelationProperty } from '@neo/domain/valueFormats/Relation.ts';
import { computed } from 'vue';
import { RelationValue } from '@neo/domain/Value.ts';

const props = defineProps<ValueDisplayProps<RelationProperty>>();

const todo = computed( (): string => {
	if ( props.value instanceof RelationValue ) {
		return 'TODO:' + props.value.targetIds.join( ', ' );
	}

	return '';
} );
</script>
