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
import { ValueType } from '@/domain/Value.ts';
import { computed } from 'vue';
import { UrlProperty } from '@/domain/propertyTypes/Url.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<UrlProperty>>();

const urls = computed( () => {
	if ( props.value.type !== ValueType.String ) {
		return '';
	}
	return props.value.parts.filter( ( url ) => url.trim() !== '' );
} );
</script>
