<template>
	<div
		ref="rootRef"
		class="ext-neowiki-language-picker"
		@focusout="closeWhenFocusLeaves"
		@keyup.esc="closeOnEscape"
	>
		<!-- A press leaves the focus where it is: in Safari a pressed button does not take the focus
			at all, so the field the user was in would lose it to nothing, and a row emptied of its
			text would be dropped before the click reached this button. -->
		<CdxButton
			ref="buttonRef"
			class="ext-neowiki-language-picker__button"
			weight="quiet"
			type="button"
			:aria-expanded="open"
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

		<!-- Shown rather than created, the way a Codex menu is: useFloatingMenu positions an element
			that is already there, and one appearing on open would be painted once where it happens to
			sit before being moved. -->
		<div
			v-show="open"
			:id="panelId"
			ref="panelRef"
			class="ext-neowiki-language-picker__panel"
			@mousedown="keepFocusInSearch"
		>
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
			/>
		</div>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, onBeforeUnmount, onMounted, ref, watch } from 'vue';
import { CdxButton, CdxIcon, CdxMenu, CdxSearchInput, useFloatingMenu, useGeneratedId } from '@wikimedia/codex';
import type { MenuItemData } from '@wikimedia/codex';
import { cdxIconExpand } from '@wikimedia/codex-icons';
import {
	type LanguageOption,
	languageName,
	languageOptions,
	languagesByPreference,
	matchingLanguages,
	readerLanguageTags,
	typedLanguageTag
} from '@/presentation/mediaWikiLanguages.ts';

// Codex renders every menu item it is given, open or closed, so the menu is handed MediaWiki's
// several hundred languages a page at a time, the next page once the list is scrolled to its end.
const MENU_PAGE_SIZE = 50;

// Enough that the list scrolls rather than growing the panel past the dialog it opens inside.
const VISIBLE_ITEMS = 8;

// Enough of a gap that the panel's edge is never mistaken for the field's.
const PANEL_OFFSET = 4;

// How many frames to keep trying to put the focus in the search field while the panel is placed.
const FOCUS_ATTEMPTS = 10;

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

// Sorted when the panel first opens rather than as each row's picker is set up, since most are
// never opened and sorting several hundred names takes a couple of milliseconds.
const options = computed(
	(): LanguageOption[] => languagesByPreference( languageOptions(), readerLanguageTags() )
);
const searchPlaceholder = mw.message( 'neowiki-language-picker-placeholder' ).text();

const panelId = useGeneratedId( 'ext-neowiki-language-picker-panel' );
const menuId = useGeneratedId( 'ext-neowiki-language-picker-menu' );

const rootRef = ref<HTMLElement | null>( null );
const panelRef = ref<HTMLElement | null>( null );
const buttonRef = ref<InstanceType<typeof CdxButton> | null>( null );
const menuRef = ref<InstanceType<typeof CdxMenu> | null>( null );

const open = ref( false );
const query = ref( '' );
const menuLength = ref( MENU_PAGE_SIZE );

// Written in capitals, as tags usually are. In script rather than by `text-transform`, which follows
// the page's language and turns `it` into `İT` on a Turkish page.
const shownTag = computed( (): string => props.modelValue.toUpperCase() );

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

	const items: MenuItemData[] = matchingLanguages( options.value, typed )
		.slice( 0, menuLength.value )
		.map( ( option ) => ( {
			value: option.tag,
			label: option.name,
			supportingText: option.tag.toUpperCase()
		} ) );

	const typedTag = typedLanguageTag( options.value, typed );

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

function listMore(): void {
	menuLength.value += MENU_PAGE_SIZE;
}

/**
 * Which entry the arrow keys have reached, for a screen reader to announce while focus stays in
 * the search field. Read back from the panel rather than asked of the menu: Codex mints a fresh id
 * for every entry whenever the list changes, so the id it holds for the entry the arrow keys
 * reached names an element that a later page of languages has already replaced.
 */
const highlightedId = ref<string | undefined>( undefined );

watch(
	[ menuItems, () => menuRef.value?.getHighlightedMenuItem() ],
	() => {
		highlightedId.value = panelRef.value?.querySelector( '.cdx-menu-item--highlighted' )?.id;
	},
	{ flush: 'post' }
);

const buttonElement = computed(
	(): HTMLElement | undefined => buttonRef.value?.$el as HTMLElement | undefined
);

// All useFloatingMenu reads of the element it places: whether it is showing, and its root node. It
// asks by type for a CdxMenu, the component it was written against.
const floatingPanel = computed( () => panelRef.value === null ?
	undefined :
	{ $el: panelRef.value, isExpanded: (): boolean => open.value }
);

// Anchored to the button at the end of the field, and opening from that end, so the panel reads as
// belonging to the language it changes. Flipping above when there is no room below, clamping to
// the space left, and hiding when the field scrolls out of the dialog all come with it.
useFloatingMenu(
	buttonElement,
	floatingPanel as unknown as Parameters<typeof useFloatingMenu>[1],
	{ placement: 'bottom-end', offset: PANEL_OFFSET }
);

async function toggle(): Promise<void> {
	if ( open.value ) {
		closeAndReturnFocus();
		return;
	}

	// A search half typed and then dismissed is not an answer to come back to.
	query.value = '';
	menuLength.value = MENU_PAGE_SIZE;
	open.value = true;

	await nextTick();
	await focusSearch();
}

/**
 * useFloatingMenu shows the panel only once it has placed it, a frame or more after the panel
 * opens, and a field in a hidden panel cannot take the focus. So the focus is tried each frame
 * until it takes, which on a first open below the fold is not the first try. The scroll it would
 * make is left out: that would read as the dialog scrolling, which closes the panel.
 */
async function focusSearch(): Promise<void> {
	const input = panelRef.value?.querySelector( 'input' );

	if ( !input ) {
		return;
	}

	for ( let attempt = 0; attempt < FOCUS_ATTEMPTS && open.value; attempt++ ) {
		input.focus( { preventScroll: true } );

		if ( document.activeElement === input ) {
			return;
		}

		await nextFrame();
	}
}

function nextFrame(): Promise<void> {
	return new Promise( ( resolve ) => {
		requestAnimationFrame( () => resolve() );
	} );
}

function close(): void {
	open.value = false;
}

// Without scrolling: the button may be scrolled out of view, as when the dialog scrolling is what
// closed the panel.
function closeAndReturnFocus(): void {
	close();
	buttonElement.value?.focus( { preventScroll: true } );
}

/**
 * Escape closes the panel wherever the focus is in the picker, and goes no further: Codex dialogs
 * close on the Escape keyup, which would take everything typed into the dialog with it. With the
 * panel closed, Escape is the dialog's as usual. The Escape that cancels an IME composition is
 * not a cancel.
 */
function closeOnEscape( event: KeyboardEvent ): void {
	if ( !open.value || event.isComposing ) {
		return;
	}

	event.stopPropagation();
	closeAndReturnFocus();
}

/**
 * The menu owns the arrow keys, Home and End. Space is the field's, since a language name may
 * contain one, and Escape is the picker's. Enter picks on its keyup, like Escape closes on its:
 * picking on the keydown would hand the focus back to the button, and CdxButton clicks itself on
 * the Enter keyup that then lands on it, which would open the panel straight back up. Its keydown
 * does nothing, not even submit a form the dialog might hold.
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
 * Only a language the list is offering is a pick: Codex keeps the entry the arrow keys reached
 * when the entries change under it, so Enter or Tab would otherwise choose a language the search
 * has since hidden. CdxMenu reports the language the picker already holds when that one is picked
 * again, which still ends the choice.
 */
function onSelect( tag: string | null ): void {
	if ( tag === null || !menuItems.value.some( ( item ) => item.value === tag ) ) {
		return;
	}

	closeAndReturnFocus();

	if ( tag !== props.modelValue ) {
		emit( 'update:modelValue', tag );
	}
}

/**
 * Focus leaving the picker for somewhere else on the page is the user done with it. Focus going
 * nowhere is left alone: that is the window losing focus, and the panel waits for the user's
 * return, while a press elsewhere on the page closes it from onDocumentMousedown.
 */
function closeWhenFocusLeaves( event: FocusEvent ): void {
	if ( event.relatedTarget instanceof Node && !rootRef.value?.contains( event.relatedTarget ) ) {
		close();
	}
}

/**
 * A press anywhere in the panel but its search field, such as on the field's icon or on the menu's
 * scrollbar in Firefox, would move the focus out of the panel, and so close it. Codex prevents that
 * on its menu items only.
 */
function keepFocusInSearch( event: MouseEvent ): void {
	if ( !( event.target instanceof HTMLInputElement ) ) {
		event.preventDefault();
	}
}

// mousedown rather than click: a pointer that goes down outside and up inside must not read as
// having stayed inside.
function onDocumentMousedown( event: MouseEvent ): void {
	if ( open.value && !rootRef.value?.contains( event.target as Node ) ) {
		close();
	}
}

/**
 * Scrolling what the picker sits in moves the button away from under its panel, and once it
 * leaves the visible area useFloatingMenu hides the panel, which drops the focus in it to the
 * page. So the panel closes instead, handing the focus back to the button, which scrolling does not
 * take it from. Only something the picker sits inside counts as that: the panel's own list
 * scrolling is the user browsing it, and a text field left behind scrolls its text back to the
 * start, which is not the page moving at all.
 */
function closeOnScroll( event: Event ): void {
	if ( open.value && event.target instanceof Node && event.target.contains( rootRef.value ) ) {
		closeAndReturnFocus();
	}
}

onMounted( () => {
	document.addEventListener( 'mousedown', onDocumentMousedown );
	// Captured, since scroll events do not bubble.
	document.addEventListener( 'scroll', closeOnScroll, true );
} );

onBeforeUnmount( () => {
	document.removeEventListener( 'mousedown', onDocumentMousedown );
	document.removeEventListener( 'scroll', closeOnScroll, true );
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-language-picker {
	/* Static on purpose. useFloatingMenu resolves the panel's coordinates against the offset parent
		itself, and an ancestor with a position would become the panel's containing block, where a
		scrolling dialog body clips it. */
	position: static;

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

	/* Frameless: the search field and the menu under it draw their own borders, and a second one
		around both reads as a box inside a box. Its position, width, max-height and visibility are
		written onto it by useFloatingMenu, which sizes it to the button; the floor is ours, wide
		enough for a search field and the names under it. */
	&__panel {
		position: absolute;
		z-index: @z-index-dropdown;
		box-sizing: @box-sizing-base;
		min-width: @size-1600;
		box-shadow: @box-shadow-drop-medium;
		display: flex;
		flex-direction: column;
		background-color: @background-color-base;
		/* useFloatingMenu clamps the panel's height to the room left in the viewport. The list is what
			gives way, scrolling within what is left under the search field. */
		overflow: hidden;
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
