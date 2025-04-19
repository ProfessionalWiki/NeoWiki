<template>
	<div>
		{{ values.join( ', ' ) }}
	</div>
</template>

<script setup lang="ts">
import { ValueType } from '@neo/domain/Value.ts';
import { computed } from 'vue';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { TextProperty } from '@neo/domain/propertyTypes/Text.ts';

const props = defineProps<ValueDisplayProps<TextProperty>>();

const values = computed( () => {
	if ( props.value.type !== ValueType.String ) {
		return [ '' ];
	}
	return props.value.strings.filter( ( url ) => url.trim() !== '' );
} );
</script>
