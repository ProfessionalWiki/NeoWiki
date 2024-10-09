<template>
	<div>
		<div v-for="( url, key ) in urls" :key="key">
			<a :href="url">
				{{ url }}
			</a>
		</div>
	</div>
</template>

<script setup lang="ts">
import { Value, ValueType } from '@neo/domain/Value.ts';
import { computed, PropType } from 'vue';

const props = defineProps( {
	value: {
		type: Object as PropType<Value>,
		required: true
	}
} );

const urls = computed( () => {
	if ( props.value.type !== ValueType.String ) {
		return '';
	}
	return props.value.strings.filter( ( url ) => url.trim() !== '' );
} );
</script>
