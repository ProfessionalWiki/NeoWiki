<template>
	<div>
		{{ values.join( ', ' ) }}
	</div>
</template>

<script setup lang="ts">
import { Value, ValueType } from '@neo/domain/Value.ts';
import { PropType, computed } from 'vue';

const props = defineProps( {
	value: {
		type: Object as PropType<Value>,
		required: true
	}
} );

const values = computed( () => {
	if ( props.value.type !== ValueType.String ) {
		return [ '' ];
	}
	return props.value.strings.filter( ( url ) => url.trim() !== '' );
} );
</script>
