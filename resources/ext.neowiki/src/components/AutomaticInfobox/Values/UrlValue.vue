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
import { StringValue } from '@neo/domain/Value.ts';
import { computed, PropType } from 'vue';

const props = defineProps( {
	value: {
		type: Object as PropType<StringValue>,
		required: true
	}
} );

const urls = computed( () => props.value.strings.filter( ( url ) => url.trim() !== '' ) );
</script>
