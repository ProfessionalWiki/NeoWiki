<template>
	<div class="ext-neowiki-edtf-value">
		<span
			v-for="( part, index ) in parts"
			:key="index"
		><template v-if="index > 0">, </template>{{ part }}<span
			v-if="readings[index]"
			class="ext-neowiki-edtf-value__humanized"
		> ({{ readings[index] }})</span></span>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { ValueType } from '@/domain/Value.ts';
import type { EdtfProperty } from '@/domain/propertyTypes/Edtf.ts';
import { userLanguageTag } from '@/presentation/mediaWikiLanguages.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';

const props = defineProps<ValueDisplayProps<EdtfProperty>>();

const language = userLanguageTag();

const edtfHumanizer = NeoWikiServices.getEdtfHumanizer();

const parts = computed( (): string[] => {
	if ( props.value.type !== ValueType.String ) {
		return [];
	}
	return props.value.parts.filter( ( part ) => part.trim() !== '' );
} );

const readings = ref<( string | null )[]>( [] );

let requestId = 0;

watch( parts, async ( current ) => {
	const request = ++requestId;
	const results = await Promise.all(
		current.map( ( part ) => edtfHumanizer.humanize( part, language )
			.catch( () => null ) )
	);
	if ( request === requestId ) {
		readings.value = results;
	}
}, { immediate: true } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-edtf-value__humanized {
	color: @color-subtle;
	font-style: italic;
}
</style>
