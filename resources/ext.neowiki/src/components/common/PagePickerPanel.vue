<template>
	<div class="ext-neowiki-page-picker-panel">
		<CdxSearchInput
			v-model="entered"
			class="ext-neowiki-page-picker-panel__search"
			:placeholder="$i18n( 'neowiki-page-picker-placeholder' ).text()"
			:aria-label="props.ariaLabel"
			:aria-controls="menuId"
			:aria-activedescendant="highlightedId"
			aria-autocomplete="list"
			role="combobox"
			aria-expanded="true"
			@keydown="onKeydown"
			@keyup.enter="pickHighlighted"
		/>

		<CdxMenu
			:id="menuId"
			ref="menuRef"
			v-model:selected="selectedValue"
			class="ext-neowiki-page-picker-panel__menu"
			:menu-items="pageSearch.menuItems.value"
			:footer="pageSearch.createItem.value ?? undefined"
			:expanded="expanded"
			:show-pending="pageSearch.status.value === 'pending'"
			:visible-item-limit="VISIBLE_ITEMS"
			:show-no-results-slot="nothingWasFound"
			@update:selected="onSelected"
		>
			<template #no-results>
				{{ $i18n( 'neowiki-page-picker-no-results' ).text() }}
			</template>
		</CdxMenu>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, toRef, watch, onMounted } from 'vue';
import { CdxMenu, CdxSearchInput, useGeneratedId } from '@wikimedia/codex';
import { usePageSearch } from '@/composables/usePageSearch.ts';
import type { PinnedPage } from '@/composables/usePageSearch.ts';
import type { PageChoice } from '@/components/common/PageChoice.ts';

interface PagePickerPanelProps {
	/** Left out of the results, such as the page a Subject is already on. */
	excludedPageId?: number;
	/** Offer only pages that exist, leaving out the option to create the text as a new page. */
	existingPagesOnly?: boolean;
	/** Offer nothing but the page to create, naming it rather than choosing among pages. */
	newPageOnly?: boolean;
	ariaLabel?: string;
	/**
	 * Pages offered above the results while nothing has been typed, such as the one the dialog was
	 * opened on. The wording is the host's, since only the host knows why a page is worth a
	 * shortcut.
	 */
	pinnedPages?: PinnedPage[];
}

const props = withDefaults(
	defineProps<PagePickerPanelProps>(),
	{
		excludedPageId: undefined,
		existingPagesOnly: false,
		newPageOnly: false,
		ariaLabel: undefined,
		pinnedPages: () => []
	}
);

const emit = defineEmits<{
	'update:selected': [ value: PageChoice ];
}>();

// Enough that the list scrolls rather than growing the panel past the dialog it opens inside.
const VISIBLE_ITEMS = 6;

const menuId = useGeneratedId( 'ext-neowiki-page-picker-panel-menu' );

const entered = ref( '' );
const selectedValue = ref<string | null>( null );
const menuRef = ref<InstanceType<typeof CdxMenu> | null>( null );

// Codex measures the room a pinned footer needs when the menu becomes expanded, and this one is
// never anything else — so it starts closed for exactly one tick to earn that measurement. Held
// open afterwards, ignoring the menu's own requests to close, since closing is the host's to do.
const expanded = ref( false );

onMounted( () => {
	expanded.value = true;
} );

const trimmedText = computed( (): string => entered.value.trim() );

const pageSearch = usePageSearch( {
	typedText: trimmedText,
	existingPagesOnly: toRef( props, 'existingPagesOnly' ),
	newPageOnly: toRef( props, 'newPageOnly' ),
	pinnedPages: toRef( props, 'pinnedPages' ),
	excludedPageId: toRef( props, 'excludedPageId' )
} );

watch( trimmedText, ( text ) => {
	if ( text === '' ) {
		pageSearch.clear();
		return;
	}

	pageSearch.search( text );
} );

// The menu owns the arrow keys and Enter. Space is the field's, since a page title may contain
// one; Home and End are the caret's, which is in the field rather than in the list; and Escape
// belongs to whatever opened this panel.
const FIELD_KEYS = [ ' ', 'Home', 'End', 'Escape' ];

function onKeydown( event: KeyboardEvent ): void {
	// Picked on the keyup instead: picking closes the panel, which hands the focus back to the
	// button that opened it, and CdxButton clicks itself on the Enter keyup that then lands on it
	// - which would open the panel straight back up.
	if ( event.key === 'Enter' ) {
		event.preventDefault();
		return;
	}

	if ( FIELD_KEYS.includes( event.key ) ) {
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
 * The search having come back with nothing, as against not having been asked yet. CdxLookup says
 * this for itself once something has been typed into it; a bare menu has no field to ask, so the
 * condition is stated here — without it a picker restricted to existing pages, which has no create
 * option to fall back on, answers a fruitless search with an empty box and no words at all.
 */
const nothingWasFound = computed( (): boolean =>
	pageSearch.status.value === 'done' && pageSearch.menuItems.value.length === 0 );

/**
 * The focus stays in the field while the arrow keys move the highlight, so the field is what has to
 * name the row they reached. Read back from the DOM rather than asked of the menu, which hands out
 * a fresh id for every entry whenever the list changes. Watched rather than computed: the arrow
 * keys move the menu's own state, and nothing around it re-renders.
 */
const highlightedId = ref<string | undefined>( undefined );

watch(
	[ () => pageSearch.menuItems.value, () => menuRef.value?.getHighlightedMenuItem() ],
	() => {
		highlightedId.value = menuRef.value?.$el.querySelector( '.cdx-menu-item--highlighted' )?.id;
	},
	{ flush: 'post' }
);

function onSelected( value: string | null ): void {
	if ( value === null ) {
		return;
	}

	// Codex holds the entry the arrow keys reached as a snapshot and never re-resolves it against
	// the list, so Enter after the results changed under the highlight would otherwise choose a
	// page the search has since dropped. The create option lives in the footer, outside the list.
	const offered = pageSearch.menuItems.value.some( ( item ) => item.value === value ) ||
		value === pageSearch.createItem.value?.value;

	if ( !offered ) {
		return;
	}

	const chosen = pageSearch.pageFor( value );

	// Held by nothing: this panel reports a choice and its host closes it, so there is no selection
	// to keep and an item picked twice running has to report twice.
	selectedValue.value = null;

	if ( chosen !== null ) {
		emit( 'update:selected', chosen );
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-page-picker-panel {
	display: flex;
	flex-direction: column;

	/* The field is the top of one shape and the menu the bottom of it, so the corners where they
		meet are squared and the two borders between them collapse into the one line. Codex already
		squares the menu's top for exactly this, since that is how it sits under a lookup. */
	&__search .cdx-text-input__input {
		border-end-start-radius: 0;
		border-end-end-radius: 0;
	}

	/* Codex floats a menu under the field it belongs to, positioned by useFloatingMenu. Here it is
		the lower half of a panel instead, so it goes back into the flow — which is what makes its
		height part of the popover's, and so what lets the popover grow upwards from the button
		rather than opening over it. */
	&__menu.cdx-menu {
		position: static;
		max-width: none;
		margin-block-start: -@border-width-base;
		box-shadow: none;
	}
}
</style>
