<template>
	<div>
		TODO: {{ ( statement.value as RelationValue ).targetIds.join( ', ' ) }}
	</div>
</template>

<script setup lang="ts">
import { PropType } from 'vue';
import { Statement } from '@neo/domain/Statement.ts';
import { RelationValue } from '@neo/domain/Value.ts';

defineProps( {
	statement: {
		type: Object as PropType<Statement>,
		required: true
	}
} );
</script>
