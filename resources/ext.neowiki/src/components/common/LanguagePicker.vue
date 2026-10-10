<template>
	<NeoPopover
		v-model:open="open"
		placement="bottom-end"
		:offset="4"
	>
		<!-- A press leaves the focus where it is: in Safari a pressed button does not take the focus
			at all, so the field the user was in would lose it to nothing, and a row emptied of its
			text would be dropped before the click reached this button. -->
		<template #trigger="{ setTrigger, toggle, open: isOpen, panelId }">
			<CdxButton
				:ref="setTrigger"
				class="ext-neowiki-language-picker__button"
				weight="quiet"
				type="button"
				aria-haspopup="listbox"
				:aria-expanded="isOpen"
				:aria-controls="panelId"
				:aria-label="buttonLabel"
				:title="buttonLabel"
				@mousedown.prevent
				@click="toggle"
			>
				<span class="ext-neowiki-language-picker__tag">{{ shownTag }}</span>
				<CdxIcon
					:icon="cdxIconExpand"
					size="x-small"
				/>
			</CdxButton>
		</template>

		<CdxSearchInput
			v-model="query"
			class="ext-neowiki-language-picker__search"
			:placeholder="searchPlaceholder"
			:aria-label="searchPlaceholder"
			:aria-controls="menuId"
			:aria-activedescendant="highlightedId"
			aria-autocomplete="list"
			role="combobox"
			aria-expanded="true"
			@keydown="onSearchKeydown"
			@keyup.enter="pickHighlighted"
		/>
		<!-- Out of the tab order: the list is reached with the arrow keys from the search field, and
			a scrollable list Chrome would otherwise stop Tab on has no name and no use there. -->
		<CdxMenu
			:id="menuId"
			ref="menuRef"
			tabindex="-1"
			class="ext-neowiki-language-picker__menu"
			:selected="props.modelValue"
			:menu-items="menuItems"
			:expanded="open"
			:visible-item-limit="VISIBLE_ITEMS"
			@update:selected="onSelect"
			@load-more="listMore"
		>
			<template #no-results>
				{{ noResultsLabel }}
			</template>
		</CdxMenu>
	</NeoPopover>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxButton, CdxIcon, CdxMenu, CdxSearchInput, useGeneratedId } from '@wikimedia/codex';
import NeoPopover from '@/components/common/NeoPopover.vue';
import type { MenuItemData } from '@wikimedia/codex';
import { cdxIconExpand } from '@wikimedia/codex-icons';
import {
	languageName,
	languagesByPreference,
	matchingLanguages,
	shownLanguageTag,
	typedLanguageTag
} from '@/presentation/mediaWikiLanguages.ts';

// Codex renders every menu item it is given, open or closed, so the menu is handed MediaWiki's
// several hundred languages a page at a time, the next page once the list is scrolled to its end.
const MENU_PAGE_SIZE = 50;

// Enough that the list scrolls rather than growing the panel past the dialog it opens inside.
const VISIBLE_ITEMS = 8;

interface LanguagePickerProps {
	/** The committed language, as a BCP 47 tag. */
	modelValue: string;
	/**
	 * Names the field the picker stands in, such as "Title language 2". Its button is the only tab
	 * stop that field has, so this is what names the button.
	 */
	label: string;
}

const props = defineProps<LanguagePickerProps>();

const emit = defineEmits<{
	'update:modelValue': [ tag: string ];
}>();

const searchPlaceholder = mw.message( 'neowiki-language-picker-placeholder' ).text();
const noResultsLabel = mw.message( 'neowiki-language-picker-no-results' ).text();

const menuId = useGeneratedId( 'ext-neowiki-language-picker-menu' );

const menuRef = ref<InstanceType<typeof CdxMenu> | null>( null );

const open = ref( false );
const query = ref( '' );
const menuLength = ref( MENU_PAGE_SIZE );

const shownTag = computed( (): string => shownLanguageTag( props.modelValue ) );

const buttonLabel = computed( (): string => mw.message(
	'neowiki-language-picker-button',
	props.label,
	languageName( props.modelValue ) ?? props.modelValue,
	props.modelValue
).text() );

/**
 * The languages to choose from, narrowed by what the user is typing, and none while the panel is
 * closed, since Codex renders every entry it is given, shown or not. A well-formed tag none of
 * them carries is offered as an entry of its own, which is how a language MediaWiki has no name
 * for (`und`) is entered. Every language is therefore chosen rather than typed, so text on its way
 * to a name can never be mistaken for a tag.
 */
const menuItems = computed<MenuItemData[]>( () => {
	if ( !open.value ) {
		return [];
	}

	const typed = query.value.trim();
	const options = languagesByPreference();

	const items: MenuItemData[] = matchingLanguages( options, typed )
		.slice( 0, menuLength.value )
		.map( ( option ) => ( {
			value: option.tag,
			label: option.name,
			supportingText: shownLanguageTag( option.tag )
		} ) );

	const typedTag = typedLanguageTag( options, typed );

	if ( typedTag !== undefined ) {
		items.push( {
			value: typedTag,
			label: typed,
			description: mw.message( 'neowiki-language-picker-tag' ).text()
		} );
	}

	return items;
} );

watch( query, () => {
	menuLength.value = MENU_PAGE_SIZE;
} );

// A search half typed and then dismissed is not an answer to come back to.
watch( open, ( isOpen ) => {
	if ( isOpen ) {
		query.value = '';
		menuLength.value = MENU_PAGE_SIZE;
	}
} );

function listMore(): void {
	menuLength.value += MENU_PAGE_SIZE;
}

/**
 * Which entry the arrow keys have reached, for a screen reader to announce while focus stays in
 * the search field. Read back from the rendered list rather than asked of the menu: Codex mints a
 * fresh id for every entry whenever the list changes, so the id it holds for the entry the arrow
 * keys reached names an element that a later page of languages has already replaced. Watched
 * rather than read in `onUpdated`, which the arrow keys never reach: they move the menu's own
 * state, and the picker around it does not re-render.
 */
const highlightedId = ref<string | undefined>( undefined );

watch(
	[ menuItems, () => menuRef.value?.getHighlightedMenuItem() ],
	() => {
		highlightedId.value = menuRef.value?.$el.querySelector( '.cdx-menu-item--highlighted' )?.id;
	},
	{ flush: 'post' }
);

/**
 * The menu owns the arrow keys. Space is the field's, since a language name may contain one, and
 * Escape is the picker's; a bare Home or End never arrives, CdxTextInput keeping those for the
 * text. Enter picks on its keyup, like Escape closes on its: picking on the keydown would hand the
 * focus back to the button, and CdxButton clicks itself on the Enter keyup that then lands on it,
 * which would open the panel straight back up. Its keydown does nothing, not even submit a form
 * the dialog might hold.
 */
function onSearchKeydown( event: KeyboardEvent ): void {
	if ( event.key === 'Enter' ) {
		event.preventDefault();
		return;
	}

	if ( event.key === ' ' || event.key === 'Escape' ) {
		return;
	}

	menuRef.value?.delegateKeyNavigation( event );
}

// The Enter that ends an IME composition is not a pick.
function pickHighlighted( event: KeyboardEvent ): void {
	if ( !event.isComposing ) {
		menuRef.value?.delegateKeyNavigation( event );
	}
}

/**
 * Only a language the list is offering is a pick: Codex holds the entry the arrow keys reached as
 * a snapshot and never re-resolves it against the entries, so Enter or Tab would otherwise choose
 * a language the search has since hidden. That snapshot is also why `highlightedId` reads the id
 * back from the list. CdxMenu reports the language the picker already holds when that one is
 * picked again, which still ends the choice.
 */
function onSelect( tag: string | null ): void {
	if ( tag === null || !menuItems.value.some( ( item ) => item.value === tag ) ) {
		return;
	}

	open.value = false;

	if ( tag !== props.modelValue ) {
		emit( 'update:modelValue', tag );
	}
}

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-language-picker {
	/* Positioned so it paints over the field it sits in: Codex gives `.cdx-text-input`
		`position: relative`, and a positioned box paints above an unpositioned sibling whatever the
		source order says. Safe on the button, which is a leaf and no ancestor of the panel. */
	&__button.cdx-button {
		position: relative;
		font-size: @font-size-x-small;
		font-weight: @font-weight-normal;
	}

	/* A long name wraps; its tag moves to the next line whole rather than breaking at a hyphen. */
	&__menu .cdx-menu-item__text__supporting-text {
		white-space: nowrap;
	}

	&__search {
		flex: none;
	}

	/* Codex floats a menu under the field it belongs to. Here it is the lower half of the panel
		instead, so it goes back into the flow, and its height becomes part of the panel's. The field
		is the top of one shape and the menu the bottom of it, so the menu's top corners are square,
		as the field's are, and the two borders between them collapse into the one line. */
	&__menu.cdx-menu {
		position: static;
		border-start-start-radius: 0;
		border-start-end-radius: 0;
		flex: 1 1 auto;
		min-height: 0;
		max-width: none;
		margin-block-start: -@border-width-base;
		box-shadow: none;

		.cdx-menu__listbox {
			min-height: 0;
		}
	}
}
</style>
