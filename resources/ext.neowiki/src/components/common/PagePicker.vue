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
import { ref, computed, toRef, watch } from 'vue';
import { CdxLookup } from '@wikimedia/codex';
import { cdxIconArticles } from '@wikimedia/codex-icons';
import type { MenuItemData } from '@wikimedia/codex';
import { usePageSearch } from '@/composables/usePageSearch.ts';
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

const selectedValue = ref<string | null>( null );
const inputText = ref<string | number>( '' );
// The title the field is currently showing for its selection, so text the user typed can be told
// apart from the label Codex writes there itself.
const selectedName = ref( '' );

const typedText = computed( (): string => {
	const text = String( inputText.value ?? '' ).trim();

	return text === selectedName.value ? '' : text;
} );

const pageSearch = usePageSearch( {
	typedText,
	excludedPageId: toRef( props, 'excludedPageId' )
} );

// The lookup's menu has no footer to pin it to — menuConfig, all CdxLookup passes on to CdxMenu,
// does not carry one — so the create option rides at the end of the list here. The icon is put on
// here rather than in the search itself: it says "page" beside a title, which is worth saying in a
// field that takes several kinds of answer and says nothing in a panel where every row is a page.
const menuItems = computed( (): MenuItemData[] => {
	const found = pageSearch.menuItems.value.map( ( item ) => pageSearch.isSentinel(
		String( item.value ) ) ? item : { ...item, icon: cdxIconArticles } );
	const create = pageSearch.createItem.value;

	return create === null ? found : [ ...found, create ];
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
		pageSearch.clear();
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

	await pageSearch.search( text );
}

function onValueSelected( value: string | null ): void {
	// Codex drops its own selection whenever the field's text changes, including the change it makes
	// itself: picking an item writes that item's label into the field, which immediately clears the
	// selection that was just made. Forwarding that would undo every pick whose label differs from
	// what was typed. Clearing the host's choice belongs to the field-value watcher above.
	if ( value === null ) {
		return;
	}

	const chosen = pageSearch.pageFor( value );

	// Codex refuses to select a disabled item; the picker does not rely on that to keep its own
	// sentinels out of a choice. Put back what the field already held, before anything awaits.
	if ( pageSearch.isSentinel( value ) ) {
		selectedValue.value = null;

		if ( chosen !== null ) {
			// Recorded before the field is written: the watcher above reads this to tell Codex's
			// own writing apart from the user typing.
			selectedName.value = chosen.title;
			inputText.value = chosen.title;
			emit( 'update:selected', chosen );
		}

		return;
	}

	// Recorded before Codex writes the picked item's label into the field, where it would
	// otherwise read as text the user had typed and rename the create option.
	selectedName.value = chosen?.title ?? '';

	pageSearch.status.value = 'idle';
	emit( 'update:selected', chosen );
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
		the only one — a line above a lone entry reads as a mistake. This menu draws the line
		itself: `menuConfig`, all CdxLookup passes on to CdxMenu, has no footer in its type, and
		a footer item is selectable like any other anyway. Scoped to a menu that has a create
		option, since otherwise the line would fall above the last result. */
	/* The create option is always the last item, and is set off from the results above it. */
	.cdx-menu__listbox > .cdx-menu-item:last-child:not( :first-child ) {
		border-top: @border-subtle;
	}
}
</style>
