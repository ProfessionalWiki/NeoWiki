<template>
	<div>
		{{ values.join( ', ' ) }}
	</div>
</template>

<script setup lang="ts">
import { StringValue } from '@neo/domain/Value.ts';
import { PropType, computed } from 'vue';

const props = defineProps( {
	value: {
		type: Object as PropType<StringValue>,
		required: true
	}
} );

const values = computed( () => props.value.strings.filter( ( value ) => value.trim() !== '' ) );
</script>
