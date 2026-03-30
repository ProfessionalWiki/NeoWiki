<template>
	<div>
		{{ formattedValue }}
	</div>
</template>

<script setup lang="ts">
import { ValueType } from '@/domain/Value.ts';
import { computed } from 'vue';
import { DateTimeProperty } from '@/domain/propertyTypes/DateTime.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<DateTimeProperty>>();

const formattedValue = computed( (): string => {
	if ( props.value.type !== ValueType.String ) {
		return '';
	}

	const dateString = props.value.parts[ 0 ];
	if ( !dateString ) {
		return '';
	}

	const date = new Date( dateString );
	if ( isNaN( date.getTime() ) ) {
		return dateString;
	}

	return date.toLocaleString( undefined, { timeZone: 'UTC' } );
} );
</script>
