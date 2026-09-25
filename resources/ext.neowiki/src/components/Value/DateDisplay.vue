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
import { DateProperty } from '@/domain/propertyTypes/Date.ts';
import { formatDateForDisplay } from '@/domain/propertyTypes/dateText.ts';
import { parsePartialDate } from '@/domain/propertyTypes/PartialDate.ts';
import { userLanguageTag } from '@/presentation/mediaWikiLanguages.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<DateProperty>>();

const language = userLanguageTag();

const rawValue = computed( (): string => {
	if ( props.value.type !== ValueType.String ) {
		return '';
	}
	return props.value.parts[ 0 ] ?? '';
} );

const parsedIso = computed( (): string | null => {
	const raw = rawValue.value;
	return parsePartialDate( raw ) === null ? null : raw;
} );

const formattedValue = computed( (): string => (
	parsedIso.value === null ? '' : formatDateForDisplay( parsedIso.value, language )
) );
</script>
