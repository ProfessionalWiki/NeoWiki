<template>
	<time
		v-if="parsedIso !== null"
		:datetime="parsedIso"
	>
		{{ formattedValue }}
	</time>
	<span v-else>{{ fallbackText }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { ValueType } from '@/domain/Value.ts';
import { DateTimeProperty, parseStrictDateTime } from '@/domain/propertyTypes/DateTime.ts';
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

const formattedValue = computed( (): string => {
	const iso = parsedIso.value;
	if ( iso === null ) {
		return '';
	}
	// Per-component options rather than dateStyle+timeStyle: ECMA-402 throws
	// when dateStyle/timeStyle is combined with timeZoneName.
	return new Date( iso ).toLocaleString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
		second: '2-digit',
		timeZoneName: 'short'
	} );
} );

const fallbackText = computed( (): string => ( parsedIso.value === null ? rawValue.value : '' ) );
</script>
