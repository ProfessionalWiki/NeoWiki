<template>
	<div class="ext-neowiki-ui ext-neowiki-language-input">
		<LanguagePicker
			:model-value="props.modelValue"
			placement="bottom-end"
			@update:model-value="commit"
		>
			<template #default="{ expanded: searchExpanded, controls, toggle, close }">
				<!-- A select, rendered as CdxSelect renders one, that shows the tag rather than the name to
					fit in the end of the text field. The user turning to it is done with the search.
					Holding no language, it is the search's own trigger instead. -->
				<div
					ref="handleRef"
					class="ext-neowiki-language-input__handle"
					role="combobox"
					tabindex="0"
					:aria-label="props.label"
					:aria-controls="opensSearch ? controls : menuId"
					:aria-expanded="opensSearch ? searchExpanded : expanded"
					:aria-activedescendant="highlightedId"
					:title="handleText"
					@click="opensSearch ? toggle() : toggleList()"
					@blur="expanded = false"
					@keydown="( event: KeyboardEvent ) => onKeydown( event, toggle )"
					@focus="onHandleFocus( close )"
				>
					<CdxIcon
						v-if="props.modelValue === null"
						:icon="cdxIconLanguage"
						size="small"
					/>
					<span
						v-else
						aria-hidden="true"
					>
						{{ shownLanguageTag( props.modelValue ) }}
					</span>
					<span class="ext-neowiki-language-input__name">{{ handleText }}</span>
					<CdxIcon
						:icon="cdxIconExpand"
						size="x-small"
					/>
				</div>
				<CdxMenu
					:id="menuId"
					ref="menuRef"
					v-model:expanded="expanded"
					class="ext-neowiki-language-input__menu"
					:selected="props.modelValue"
					:menu-items="menuItems"
					@update:selected="( value: MenuItemValue | null ) => onSelect( value, toggle )"
				/>
			</template>
		</LanguagePicker>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { CdxIcon, CdxMenu, useFloatingMenu, useGeneratedId } from '@wikimedia/codex';
import type { MenuItemData, MenuItemValue } from '@wikimedia/codex';
import { cdxIconExpand, cdxIconLanguage } from '@wikimedia/codex-icons';
import LanguagePicker from '@/components/common/LanguagePicker.vue';
import { dataLanguageTags, languageNameOrTag, shownLanguageTag } from '@/presentation/languages.ts';

// Never a language tag, which has no spaces.
const OTHER_LANGUAGE = 'other language';

// The keys that open a select's list.
const SEARCH_OPENING_KEYS = [ 'Enter', ' ', 'ArrowDown', 'ArrowUp' ];

interface LanguageInputProps {
	/**
	 * The language, as a BCP 47 tag, or null where the user has yet to pick one from the search,
	 * every language the list offers being taken.
	 */
	modelValue: string | null;
	/** Names the field, such as "Title language 2". */
	label: string;
}

const props = defineProps<LanguageInputProps>();

const emit = defineEmits<{
	'update:modelValue': [ tag: string ];
}>();

const dataLanguages = dataLanguageTags();

const otherLanguageText = mw.message( 'neowiki-language-input-other' ).text();

const chooseLanguageText = mw.message( 'neowiki-language-input-choose' ).text();

const menuId = useGeneratedId( 'ext-neowiki-language-input-menu' );

const handleRef = ref<HTMLElement | null>( null );
const menuRef = ref<InstanceType<typeof CdxMenu>>();

const expanded = ref( false );

const opensSearch = computed( (): boolean => props.modelValue === null );

const handleText = computed(
	(): string => props.modelValue === null ? chooseLanguageText : languageNameOrTag( props.modelValue )
);

const menuItems = computed( (): MenuItemData[] => {
	const tags = props.modelValue === null || dataLanguages.includes( props.modelValue ) ?
		dataLanguages :
		[ ...dataLanguages, props.modelValue ];

	return [
		...tags.map( ( tag ) => ( { value: tag, label: languageNameOrTag( tag ) } ) ),
		{ value: OTHER_LANGUAGE, label: otherLanguageText }
	];
} );

const highlightedId = computed( () => menuRef.value?.getHighlightedMenuItem()?.id );

// Wrapped the way SearchablePicker wraps its trigger, for the reason it gives.
const anchor = computed( () => handleRef.value === null ? undefined : { $el: handleRef.value } );

// CdxSelect centres its list under the handle, so a list wider than the tag would run past the end
// of the text field; opening from the tag's end keeps it over the field. The casts are
// DataExportButtons.vue's, which explains them.
useFloatingMenu(
	anchor as unknown as Parameters<typeof useFloatingMenu>[0],
	menuRef,
	{ placement: 'bottom-end', offset: 4 }
);

function toggleList(): void {
	expanded.value = !expanded.value;
	handleRef.value?.focus();
}

function onKeydown( event: KeyboardEvent, toggleSearch: () => void ): void {
	if ( !opensSearch.value ) {
		menuRef.value?.delegateKeyNavigation( event, { characterNavigation: true } );
		return;
	}

	if ( SEARCH_OPENING_KEYS.includes( event.key ) ) {
		event.preventDefault();
		toggleSearch();
	}
}

// Closing the search here would have the click that follows the focus open it again.
function onHandleFocus( closeSearch: () => void ): void {
	if ( !opensSearch.value ) {
		closeSearch();
	}
}

function onSelect( value: MenuItemValue | null, openSearch: () => void ): void {
	if ( value === OTHER_LANGUAGE ) {
		openSearch();
	} else if ( typeof value === 'string' && value !== props.modelValue ) {
		commit( value );
	}
}

function commit( tag: string ): void {
	emit( 'update:modelValue', tag );
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-language-input {
	/* A quiet button's look. Positioned so it paints over the text field it sits in, which Codex
		positions: safe on the handle, which is no ancestor of the list or the search panel. */
	&__handle {
		position: relative;
		display: flex;
		align-items: center;
		gap: @spacing-25;
		box-sizing: @box-sizing-base;
		min-height: @min-size-interactive-pointer;
		border: @border-width-base @border-style-base @border-color-transparent;
		border-radius: @border-radius-base;
		padding: 0 ( @spacing-75 - @border-width-base );
		color: @color-base;
		font-size: @font-size-x-small;
		cursor: @cursor-base--hover;
		transition-property: @transition-property-base;
		transition-duration: @transition-duration-base;

		&:hover {
			background-color: @background-color-button-quiet--hover;
		}

		&:active {
			border-color: @border-color-interactive;
			background-color: @background-color-button-quiet--active;
			color: @color-emphasized;
		}

		&:focus-visible {
			border-color: @border-color-progressive--focus;
			box-shadow: @box-shadow-inset-small @box-shadow-color-progressive--focus;
			outline: @outline-base--focus;
		}
	}

	&__name {
		.ext-neowiki-visually-hidden();
	}

	/* Codex sizes the list to the handle, which is narrower than the names in it. */
	&__menu.cdx-menu {
		min-width: max-content;
	}
}
</style>
