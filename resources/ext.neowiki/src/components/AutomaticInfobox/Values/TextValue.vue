<template>
	<div>
		{{ values.join( ', ' ) }}
	</div>
</template>

<script setup lang="ts">
import { StringValue } from '@neo/domain/Value.ts';
import { PropType, computed } from 'vue';
import { Statement } from '@neo/domain/Statement.ts';

const props = defineProps( {
	statement: {
		type: Object as PropType<Statement>,
		required: true
	}
} );

const values = computed( () => ( props.statement.value as StringValue ).strings.filter( ( value ) => value.trim() !== '' ) );
</script>
