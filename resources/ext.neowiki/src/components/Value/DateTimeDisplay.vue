<template>
	<time
		v-if="parsedIso !== null"
		:datetime="parsedIso"
	>
		{{ formattedValue }}
	</time>
	<span v-else>{{ rawValue }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ValueType } from '@/domain/Value.ts';
import { DateTimeProperty, formatDateTimeForDisplay, parseStrictDateTime } from '@/domain/propertyTypes/DateTime.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<DateTimeProperty>>();

const rawValue = computed( (): string => {
	if ( props.value.type !== ValueType.String ) {
		return '';
	}
	return props.value.parts[ 0 ] ?? '';
} );

const parsedIso = computed( (): string | null => {
	const raw = rawValue.value;
	return raw !== '' && parseStrictDateTime( raw ) !== null ? raw : null;
} );

const formattedValue = computed( (): string => (
	parsedIso.value === null ? '' : formatDateTimeForDisplay( parsedIso.value )
) );
</script>
