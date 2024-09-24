<template>
	<div>
		{{ ( statement.value as NumberValue ).number }}
	</div>
</template>

<script setup lang="ts">
import { NumberValue } from '@neo/domain/Value.ts';
import { PropType } from 'vue';
import { Statement } from '@neo/domain/Statement.ts';

defineProps( {
	statement: {
		type: Object as PropType<Statement>,
		required: true
	}
} );
</script>
