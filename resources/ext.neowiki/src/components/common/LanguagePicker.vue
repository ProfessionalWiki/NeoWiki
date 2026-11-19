<template>
	<SearchablePicker
		ref="pickerRef"
		class="ext-neowiki-language-picker"
		:model-value="props.modelValue"
		:placement="props.placement"
		:menu-items="menuItems"
		:pending="false"
		:search-placeholder="searchPlaceholder"
		:no-results-label="noResultsLabel"
		@update:model-value="emit( 'update:modelValue', $event )"
		@update:query="query = $event"
	>
		<template #default="trigger">
			<slot v-bind="trigger" />
		</template>
	</SearchablePicker>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import type { MenuItemData } from '@wikimedia/codex';
import SearchablePicker, { type SearchablePickerTrigger } from '@/components/common/SearchablePicker.vue';
import {
	languageOptions,
	matchingLanguages,
	shownLanguageTag,
	typedLanguageTag
} from '@/presentation/languages.ts';

interface LanguagePickerProps {
	/** The committed language, as a BCP 47 tag, or null for none. */
	modelValue: string | null;
	/**
	 * The edge of the trigger the panel opens from, so that it reads as belonging to it: the end of a
	 * trigger at the end of a field, the start of one that starts a row.
	 */
	placement: 'bottom-start' | 'bottom-end';
	/** Languages listed but not to be picked, as BCP 47 tags, such as the ones a list already holds. */
	disabledLanguages?: readonly string[];
}

const props = withDefaults( defineProps<LanguagePickerProps>(), {
	disabledLanguages: () => []
} );

const emit = defineEmits<{
	'update:modelValue': [ tag: string ];
}>();

defineSlots<{
	default( props: SearchablePickerTrigger ): unknown;
}>();

const searchPlaceholder = mw.message( 'neowiki-language-picker-placeholder' ).text();
const noResultsLabel = mw.message( 'neowiki-language-picker-no-results' ).text();

const pickerRef = ref<InstanceType<typeof SearchablePicker> | null>( null );

const query = ref( '' );

/**
 * The languages to choose from, narrowed by what the user is typing. A well-formed tag none of them
 * carries is offered as an entry of its own, which is how a language the list lacks (`gsw`) is
 * entered. Every language is therefore chosen rather than typed, so text on its way to a name can
 * never be mistaken for a tag.
 */
const menuItems = computed<MenuItemData[]>( () => {
	const typed = query.value.trim();
	const options = languageOptions();

	const items: MenuItemData[] = matchingLanguages( options, typed )
		.map( ( option ) => ( {
			value: option.tag,
			label: option.name,
			supportingText: shownLanguageTag( option.tag ),
			disabled: props.disabledLanguages.includes( option.tag )
		} ) );

	const typedTag = typedLanguageTag( options, typed );

	if ( typedTag !== undefined ) {
		items.push( {
			value: typedTag,
			label: typed,
			description: mw.message( 'neowiki-language-picker-tag' ).text(),
			disabled: props.disabledLanguages.includes( typedTag )
		} );
	}

	return items;
} );

defineExpose( {
	focusTrigger(): void {
		pickerRef.value?.focusTrigger();
	}
} );
</script>

<style lang="less">
.ext-neowiki-language-picker {
	/* A long name wraps; its tag moves to the next line whole rather than breaking at a hyphen. */
	.ext-neowiki-searchable-picker__menu .cdx-menu-item__text__supporting-text {
		white-space: nowrap;
	}
}
</style>
