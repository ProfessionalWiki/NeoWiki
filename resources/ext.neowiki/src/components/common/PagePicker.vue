<template>
	<div class="ext-neowiki-page-picker">
		<CdxLookup
			v-model:selected="selectedValue"
			v-model:input-value="inputText"
			:menu-items="menuItems"
			:placeholder="$i18n( 'neowiki-page-picker-placeholder' ).text()"
			:aria-label="props.ariaLabel"
			@update:selected="onValueSelected"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, computed, watch } from 'vue';
import { CdxLookup } from '@wikimedia/codex';
import type { MenuItemData } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconArticles } from '@wikimedia/codex-icons';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import type { PageChoice } from '@/components/common/PageChoice.ts';

interface PagePickerProps {
	/** Left out of the results, such as the page the Subject is already on. */
	excludedPageId?: number;
	ariaLabel?: string;
}

const props = withDefaults(
	defineProps<PagePickerProps>(),
	{
		excludedPageId: undefined,
		ariaLabel: undefined
	}
);

const emit = defineEmits<{
	'update:selected': [ value: PageChoice | null ];
}>();

// Menu values are page ids as text, so neither sentinel can collide with a result: a page id is
// always digits.
const CREATE_PAGE = '__create__';
const NO_RESULTS = '__no_results__';

const RESULT_LIMIT = 10;

const pageTitleSearch = NeoWikiServices.getPageTitleSearch();

const selectedValue = ref<string | null>( null );
const inputText = ref<string | number>( '' );
const searchResults = ref<MenuItemData[]>( [] );
// Idle until the user types, pending while the request is out, done once it has come back, so the
// menu says "nothing found" only when a search actually found nothing.
const searchStatus = ref<'idle' | 'pending' | 'done'>( 'idle' );
// The title the field is currently showing for its selection, so text the user typed can be told
// apart from the label Codex writes there itself.
const selectedName = ref( '' );
let requestSequence = 0;

const typedText = computed( (): string => {
	const text = String( inputText.value ?? '' ).trim();

	return text === selectedName.value ? '' : text;
} );

const createItem = computed( (): MenuItemData => ( {
	value: CREATE_PAGE,
	label: typedText.value === '' ?
		mw.msg( 'neowiki-page-picker-create-hint' ) :
		mw.msg( 'neowiki-page-picker-create-named', typedText.value ),
	icon: cdxIconAdd,
	disabled: typedText.value === ''
} ) );

// The create option is present from the first render and never leaves, which is what opens the
// menu on focus before anything is typed: Codex expands an empty input's menu only when the
// Lookup was built with items, and collapses it again the moment the list runs empty.
const menuItems = computed( (): MenuItemData[] => {
	const items = [ ...searchResults.value ];

	// Codex's own no-results slot is shown only for an empty menu, which the create option rules
	// out, so that case carries the same message as an item nobody can pick.
	if ( searchStatus.value === 'done' && items.length === 0 ) {
		items.push( {
			value: NO_RESULTS,
			label: mw.msg( 'neowiki-page-picker-no-results' ),
			disabled: true
		} );
	}

	items.push( createItem.value );

	return items;
} );

// The field's value, not CdxLookup's `input` event: that event re-fires after a selection carrying
// the text typed BEFORE it, which reads as the user having edited the field and would drop the
// choice the moment it was made. The v-model value is what the field actually shows.
watch( inputText, ( value ) => {
	onFieldTextChanged( String( value ?? '' ) );
} );

async function onFieldTextChanged( value: string ): Promise<void> {
	const text = value.trim();

	if ( text === '' ) {
		searchResults.value = [];
		searchStatus.value = 'idle';
		selectedName.value = '';
		emit( 'update:selected', null );
		return;
	}

	// Codex writes the picked item's label into the field. That is not the user editing anything, so
	// it neither drops the choice nor earns a second search.
	if ( text === selectedName.value ) {
		return;
	}

	// Any other change is the user moving away from what they picked, which un-picks it: Codex drops
	// its own selection only when it still holds one, and a picked create option leaves it holding
	// none.
	selectedName.value = '';
	emit( 'update:selected', null );

	searchStatus.value = 'pending';
	const currentSequence = ++requestSequence;

	try {
		const results = await pageTitleSearch.searchPageTitles( text, RESULT_LIMIT );

		if ( currentSequence !== requestSequence ) {
			return;
		}

		searchResults.value = results
			.filter( ( result ) => result.pageId !== props.excludedPageId )
			.map( ( result ) => ( {
				label: result.title,
				value: String( result.pageId ),
				icon: cdxIconArticles
			} ) );
	} catch {
		if ( currentSequence !== requestSequence ) {
			return;
		}

		searchResults.value = [];
	} finally {
		if ( currentSequence === requestSequence ) {
			searchStatus.value = 'done';
		}
	}
}

function onValueSelected( value: string | null ): void {
	if ( value === CREATE_PAGE ) {
		// Put back what the field already held, before anything awaits: Codex has just reported the
		// sentinel as the selection.
		const title = typedText.value;
		selectedValue.value = null;

		if ( title !== '' ) {
			selectedName.value = title;
			inputText.value = title;
			emit( 'update:selected', { pageId: null, title } );
		}

		return;
	}

	// Codex refuses to select a disabled item; the picker does not rely on that to keep its own
	// sentinel out of a move.
	if ( value === NO_RESULTS ) {
		selectedValue.value = null;
		return;
	}

	// Codex drops its own selection whenever the field's text changes, including the change it makes
	// itself: picking an item writes that item's label into the field, which immediately clears the
	// selection that was just made. Forwarding that would undo every pick whose label differs from
	// what was typed. Clearing the host's choice belongs to the field-value watcher above.
	if ( value === null ) {
		return;
	}

	// Recorded before Codex writes the picked item's label into the field, where it would
	// otherwise read as text the user had typed and rename the create option.
	const picked = menuItems.value.find( ( item ) => item.value === value );
	selectedName.value = String( picked?.label ?? '' );

	searchStatus.value = 'idle';
	emit( 'update:selected', { pageId: Number( value ), title: selectedName.value } );
}

</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-page-picker {
	.cdx-lookup {
		width: 100%;
	}

	/* The create option is the last item, and it offers an action rather than a result. Codex
		sets its own pinned footer item apart the same way, and skips the rule when that item is
		the only one — a line above a lone entry reads as a mistake. This menu cannot use that
		footer: it is a CdxMenu prop, and CdxLookup passes none of it through. */
	.cdx-menu__listbox > .cdx-menu-item:last-child:not( :first-child ) {
		border-top: @border-subtle;
	}
}
</style>
