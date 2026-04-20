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
import { newStringValue, ValueType } from '@/domain/Value.ts';
import { DateTimeProperty, DateTimeType } from '@/domain/propertyTypes/DateTime.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<DateTimeProperty>>();

const dateTimeType = new DateTimeType();

const rawValue = computed( (): string => {
	if ( props.value.type !== ValueType.String ) {
		return '';
	}
	return props.value.parts[ 0 ] ?? '';
} );

const parsedIso = computed( (): string | null => {
	const raw = rawValue.value;
	if ( raw === '' ) {
		return null;
	}

	const errors = dateTimeType.validate( newStringValue( raw ), props.property );
	const isValidIso = !errors.some( ( e ) => e.code === 'invalid-datetime' );

	return isValidIso ? raw : null;
} );

const formattedValue = computed( (): string => {
	const iso = parsedIso.value;
	if ( iso === null ) {
		return '';
	}
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
