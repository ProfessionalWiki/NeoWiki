<template>
	<div>
		{{ values.join( ', ' ) }}
	</div>
</template>

<script setup lang="ts">
import { ValueType } from '@/domain/Value.ts';
import { computed } from 'vue';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { TextProperty } from '@/domain/propertyTypes/Text.ts';

const props = defineProps<ValueDisplayProps<TextProperty>>();

const values = computed( () => {
	if ( props.value.type !== ValueType.String ) {
		return [ '' ];
	}
	return props.value.parts.filter( ( part ) => part.trim() !== '' );
} );
</script>
