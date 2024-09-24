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
import { Statement } from '@neo/domain/Statement.ts';

const props = defineProps( {
	statement: {
		type: Object as PropType<Statement>,
		required: true
	}
} );

const urls = computed( () => ( props.statement.value as StringValue ).strings.filter( ( url ) => url.trim() !== '' ) );
</script>
