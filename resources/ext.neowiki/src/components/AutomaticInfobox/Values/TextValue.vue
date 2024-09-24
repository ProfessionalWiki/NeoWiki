<template>
	<div>
		{{ values.join( ', ' ) }}
	</div>
</template>

<script setup lang="ts">
import { StringValue, Value } from '@neo/domain/Value.ts';
import { PropType, computed } from 'vue';

const props = defineProps( {
	value: {
		type: Object as PropType<Value>,
		required: true
	}
} );

const values = computed( () => ( props.value as StringValue ).strings.filter( ( value ) => value.trim() !== '' ) );
</script>
