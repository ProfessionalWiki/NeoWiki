<template>
	<div>
		<!-- TODO: Placeholder display -->
		<div v-for="( value, key ) in values" :key="key">
			<a href="#">
				{{ value }}
			</a>
		</div>
	</div>
</template>

<script setup lang="ts">
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { RelationProperty } from '@neo/domain/propertyTypes/Relation.ts';
import { computed } from 'vue';
import { RelationValue } from '@neo/domain/Value.ts';

const props = defineProps<ValueDisplayProps<RelationProperty>>();

const values = computed( () => {
	if ( props.value instanceof RelationValue ) {
		return props.value.relations.map( ( relation ) => relation.target );
	}

	return [];
} );
</script>
