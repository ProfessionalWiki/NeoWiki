<template>
	<div
		class="ext-neowiki-language-picker"
		@mousedown="keepFocusInField"
	>
		<CdxLookup
			:selected="selection"
			:input-value="inputText"
			:menu-items="menuItems"
			:menu-config="MENU_CONFIG"
			:placeholder="languagePlaceholder"
			:aria-label="props.ariaLabel"
			@update:input-value="onInput"
			@update:selected="onSelect"
			@blur="revertUncommittedTyping"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, ref } from 'vue';
import { CdxLookup } from '@wikimedia/codex';
import type { MenuItemData } from '@wikimedia/codex';
import { isValidLanguageTag } from '@/domain/languageTag.ts';
import { type LanguageOption, languageOptions, toLanguageTag } from '@/presentation/mediaWikiLanguages.ts';

// Codex renders every menu item it is given, so the list is capped rather than handed all of
// MediaWiki's several hundred languages. Typing narrows it, so nothing is out of reach.
const MENU_ITEM_LIMIT = 50;

// A fresh object each render would make CdxLookup re-render its whole subtree.
const MENU_CONFIG = { visibleItemLimit: 8 };

interface LanguagePickerProps {
	/** The committed language, as a BCP 47 tag. */
	modelValue: string;
	ariaLabel?: string;
}

const props = withDefaults(
	defineProps<LanguagePickerProps>(),
	{
		ariaLabel: undefined
	}
);

const emit = defineEmits<{
	'update:modelValue': [ tag: string ];
}>();

const options: LanguageOption[] = languageOptions();
const languagePlaceholder = mw.message( 'neowiki-language-picker-placeholder' ).text();

// What the user is filtering by, or null while the field is showing the committed language.
const query = ref<string | null>( null );

/**
 * The languages to choose from, narrowed by what the user is typing. A well-formed tag none of
 * them carries is offered as an entry of its own, which is how a language MediaWiki has no name
 * for (`und`) is entered. Every language is therefore chosen rather than typed, so text on its way
 * to a name can never be mistaken for a tag.
 */
const menuItems = computed<MenuItemData[]>( () => {
	const typed = ( query.value ?? '' ).trim();
	const lowered = typed.toLowerCase();

	const items: MenuItemData[] = options
		.filter( ( option ) => option.name.toLowerCase().includes( lowered ) || option.tag.includes( lowered ) )
		.slice( 0, MENU_ITEM_LIMIT )
		.map( ( option ) => ( {
			value: option.tag,
			label: option.name,
			description: option.tag
		} ) );

	if ( offersTypedTag( typed ) ) {
		items.push( {
			value: toLanguageTag( typed ),
			label: typed,
			description: mw.message( 'neowiki-language-picker-tag' ).text()
		} );
	}

	return items;
} );

/**
 * Whether the typed text is also offered as a tag of its own. Text that names a language MediaWiki
 * knows is the user naming that language rather than writing a tag, and offering both would put
 * two entries reading `Basque` in the menu, one of them storing `basque`. Text on its way to such
 * a name is not that name: `ara` is a language of its own as well as the start of `aragonés`.
 */
function offersTypedTag( typed: string ): boolean {
	if ( !isValidLanguageTag( typed ) ) {
		return false;
	}

	const tag = toLanguageTag( typed );
	const name = typed.toLowerCase();

	return !options.some(
		( option ) => option.tag === tag || option.name.toLowerCase() === name
	);
}

const inputText = computed<string>( () => query.value ?? languageDisplay( props.modelValue ) );

function languageDisplay( tag: string ): string {
	return options.find( ( option ) => option.tag === tag )?.name ?? tag;
}

// CdxLookup empties its field for a selection the menu does not hold, so the language is offered as
// a selection only while the menu holds it. The field shows it regardless, because this component
// owns the field's text.
const selection = computed<string | null>(
	() => menuItems.value.some( ( item ) => item.value === props.modelValue ) ? props.modelValue : null
);

function onInput( text: string | number ): void {
	query.value = String( text );
}

// CdxLookup reports a selection only for a menu entry the user picks, and null while they type, so
// a language typed out in full is not picked by itself.
function onSelect( tag: string | null ): void {
	if ( tag === null ) {
		return;
	}

	query.value = null;

	if ( tag !== props.modelValue ) {
		emit( 'update:modelValue', tag );
	}
}

// Text left in the field was never chosen, so the language stands as it was and the field goes
// back to showing it.
function revertUncommittedTyping(): void {
	query.value = null;
}

/**
 * CdxLookup closes its menu when the field loses focus, and Firefox moves focus away on a mousedown
 * on the menu's scrollbar, which Codex prevents on its menu items only. Keeping the default off for
 * the rest of the menu leaves the focus in the field, so dragging the scrollbar scrolls the list
 * rather than closing it.
 */
function keepFocusInField( event: MouseEvent ): void {
	if ( event.target instanceof Element && event.target.closest( '.cdx-menu' ) !== null ) {
		event.preventDefault();
	}
}
</script>
