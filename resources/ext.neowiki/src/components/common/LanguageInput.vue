<template>
	<div
		class="ext-neowiki-language-input"
		@keydown.capture="noteEscape"
		@keyup.esc="keepEscapeFromDialog"
	>
		<LanguagePicker
			:model-value="props.modelValue"
			placement="bottom-end"
			@update:model-value="commit"
		>
			<template #default="{ toggle, close }">
				<!-- The user turning to the list is done with the search. -->
				<CdxSelect
					class="ext-neowiki-language-input__select"
					:selected="props.modelValue"
					:menu-items="menuItems"
					:aria-label="props.label"
					@update:selected="( value: string | number | null ) => onSelect( value, toggle )"
					@focus="close"
				>
					<!-- Every entry's label sits in the one cell, unseen but for the chosen one, so the select
						is as wide as its widest entry: Codex makes the list as wide as the select. The chosen
						one comes from the model, not the slot's selectedMenuItem, which Codex 2 leaves empty on
						the first render. -->
					<template #label>
						<span class="ext-neowiki-language-input__label">
							<span
								v-for="item in menuItems"
								:key="item.value"
								class="ext-neowiki-language-input__width"
								aria-hidden="true"
							>{{ item.label }}</span>
							<span>{{ languageNameOrTag( props.modelValue ) }}</span>
						</span>
					</template>
				</CdxSelect>
			</template>
		</LanguagePicker>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxSelect } from '@wikimedia/codex';
import type { MenuItemData } from '@wikimedia/codex';
import LanguagePicker from '@/components/common/LanguagePicker.vue';
import { dataLanguageTags, languageNameOrTag } from '@/presentation/languages.ts';
import { useListEscapeGuard } from '@/composables/useListEscapeGuard.ts';

// Never a language tag, which has no spaces.
const OTHER_LANGUAGE = 'other language';

interface LanguageInputProps {
	/** The language, as a BCP 47 tag. */
	modelValue: string;
	/** Names the field, such as "Title language 2". */
	label: string;
}

const props = defineProps<LanguageInputProps>();

const emit = defineEmits<{
	'update:modelValue': [ tag: string ];
}>();

const dataLanguages = dataLanguageTags();

const otherLanguageText = mw.message( 'neowiki-language-input-other' ).text();

const menuItems = computed( (): MenuItemData[] => {
	const tags = dataLanguages.includes( props.modelValue ) ? dataLanguages : [ ...dataLanguages, props.modelValue ];

	return [
		...tags.map( ( tag ) => ( { value: tag, label: languageNameOrTag( tag ) } ) ),
		{ value: OTHER_LANGUAGE, label: otherLanguageText }
	];
} );

function onSelect( value: string | number | null, openSearch: () => void ): void {
	if ( value === OTHER_LANGUAGE ) {
		openSearch();
	} else if ( typeof value === 'string' && value !== props.modelValue ) {
		commit( value );
	}
}

function commit( tag: string ): void {
	emit( 'update:modelValue', tag );
}

const { noteEscape, keepEscapeFromDialog } = useListEscapeGuard();
</script>

<style lang="less">
.ext-neowiki-language-input {
	/* Codex gives every select a 256px minimum, which a narrow pane cannot hold beside the text it
		qualifies. Nested to outrank Codex's own rule. */
	&__select .cdx-select-vue__handle {
		min-width: 0;
	}

	&__label {
		display: inline-grid;

		> * {
			grid-area: 1 / 1;
		}
	}

	&__width {
		visibility: hidden;
	}
}
</style>
