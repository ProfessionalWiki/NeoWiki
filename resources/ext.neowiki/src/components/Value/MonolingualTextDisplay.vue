<template>
	<div>
		<!-- The part carries the language it is in, so the element says so: a screen reader reads it in
		that language, `:lang()` font rules match, and `dir="auto"` keeps a right-to-left text and the
		name after it in the right order. -->
		<div
			v-for="( part, index ) in readParts"
			:key="index"
			:lang="part.language"
			dir="auto"
			class="ext-neowiki-monolingual-text-display__part"
		>
			{{ readText( part ) }}
		</div>
		<CdxToggleButton
			v-if="otherParts.length > 0"
			v-model="showingOthers"
			:quiet="true"
			:aria-label="showAllLabel"
			class="ext-neowiki-monolingual-text-display__toggle"
		>
			<CdxIcon
				:icon="cdxIconLanguage"
				size="small"
			/>
			{{ otherLanguagesLabel }}
		</CdxToggleButton>
		<div
			v-for="( part, index ) in shownOtherParts"
			:key="`other-${ index }`"
			:lang="part.language"
			dir="auto"
			class="ext-neowiki-monolingual-text-display__part"
		>
			{{ namedText( part ) }}
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { CdxIcon, CdxToggleButton } from '@wikimedia/codex';
import { cdxIconLanguage } from '@wikimedia/codex-icons';
import { type MonolingualText, ValueType } from '@/domain/Value.ts';
import { ValueDisplayProps } from '@/components/Value/ValueDisplayContract.ts';
import { MonolingualTextProperty } from '@/domain/propertyTypes/MonolingualText.ts';
import { languageName, partsForReader, readerLanguageTags, userLanguageTag } from '@/presentation/mediaWikiLanguages.ts';

const props = defineProps<ValueDisplayProps<MonolingualTextProperty>>();

const showingOthers = ref( false );

const parts = computed<MonolingualText[]>(
	() => props.value.type === ValueType.MonolingualText ? props.value.parts : []
);

const readParts = computed<MonolingualText[]>( () => partsForReader( parts.value, readerLanguageTags() ) );

const otherParts = computed<MonolingualText[]>(
	() => parts.value.filter( ( part ) => !readParts.value.includes( part ) )
);

const shownOtherParts = computed<MonolingualText[]>( () => showingOthers.value ? otherParts.value : [] );

/**
 * A part in a language the reader was picked for. The language is named after the text unless it is
 * the one the interface is in, which the reader can see for themselves.
 */
function readText( part: MonolingualText ): string {
	return part.language === userLanguageTag() ? part.text : namedText( part );
}

function namedText( part: MonolingualText ): string {
	return mw.message(
		'neowiki-monolingual-text-display',
		part.text,
		languageName( part.language ) ?? part.language
	).text();
}

const otherLanguagesLabel = computed<string>(
	() => mw.message( 'neowiki-monolingual-text-other-languages', otherParts.value.length ).text()
);

const showAllLabel = computed<string>(
	() => mw.message( 'neowiki-monolingual-text-show-all', parts.value.length ).text()
);
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

// The languages left out are an aside to the value, so the control reaching them is smaller than it.
.ext-neowiki-monolingual-text-display__toggle {
	font-size: @font-size-small;
}
</style>
