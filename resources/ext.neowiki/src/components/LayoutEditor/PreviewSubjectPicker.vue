<template>
	<SearchablePicker
		class="ext-neowiki-preview-subject-picker"
		:model-value="props.subject?.id ?? null"
		placement="bottom-end"
		:menu-items="menuItems"
		:pending="searching"
		:search-placeholder="$i18n( 'neowiki-layout-preview-search-placeholder' ).text()"
		:no-results-label="searchFailure ?? $i18n( 'neowiki-layout-preview-no-results' ).text()"
		@update:model-value="emit( 'pick', $event )"
		@update:query="onQueryChanged"
		@update:open="onOpenChanged"
	>
		<template #default="{ expanded, controls, toggle }">
			<CdxButton
				class="ext-neowiki-preview-subject-picker__trigger"
				:disabled="props.disabled"
				:aria-label="$i18n( 'neowiki-layout-preview-subject', props.subject?.name ?? '' ).text()"
				:aria-expanded="expanded"
				:aria-controls="controls"
				@click="toggle"
			>
				{{ props.subject?.name ?? '' }}
				<CdxIcon :icon="cdxIconExpand" />
			</CdxButton>
		</template>
	</SearchablePicker>
</template>

<script setup lang="ts">
import { onBeforeUnmount, ref } from 'vue';
import { CdxButton, CdxIcon, type MenuItemData } from '@wikimedia/codex';
import { cdxIconExpand } from '@wikimedia/codex-icons';
import SearchablePicker from '@/components/common/SearchablePicker.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { failureMessage, summaryName } from '@/presentation/subjectSummaryTable.ts';
import { disambiguateSubjectLabels, subjectMenuItem } from '@/presentation/disambiguateSubjectLabels.ts';

export interface PreviewedSubjectChoice {
	id: string;
	name: string;
}

// A page of the search's own size: the panel shows eight and scrolls.
const SEARCH_LIMIT = 20;

// As the Subjects table's search waits, so that typing a name asks once rather than per keystroke.
const SEARCH_DELAY_MS = 300;

const props = defineProps<{
	schemaName: string;
	/** Null while there is no Subject to preview. */
	subject: PreviewedSubjectChoice | null;
	disabled: boolean;
}>();

const emit = defineEmits<{
	pick: [ subjectId: string ];
}>();

const lookup = NeoWikiServices.getSubjectSummaryLookup();

const menuItems = ref<MenuItemData[]>( [] );
const searching = ref( false );
const searchFailure = ref<string | null>( null );

let open = false;
let searchTimer: ReturnType<typeof setTimeout> | undefined;

// Only the answer to the latest search fills the menu.
let searchSequence = 0;

// Opening starts afresh, with the Subjects edited last, rather than with what the last search found.
function onOpenChanged( isOpen: boolean ): void {
	open = isOpen;
	clearTimeout( searchTimer );

	if ( isOpen ) {
		menuItems.value = [];
		search( '' );
	}
}

onBeforeUnmount( () => clearTimeout( searchTimer ) );

// The search field is emptied as the panel opens, which is not a search of its own.
function onQueryChanged( typed: string ): void {
	if ( !open ) {
		return;
	}

	clearTimeout( searchTimer );
	searchTimer = setTimeout( () => search( typed ), SEARCH_DELAY_MS );
}

async function search( typed: string ): Promise<void> {
	const sequence = ++searchSequence;
	searching.value = true;

	let items: MenuItemData[] = [];
	let failure: string | null = null;

	try {
		const summaries = await lookup.getSubjectSummaries( {
			schema: props.schemaName,
			search: typed.trim(),
			sort: 'edited',
			direction: 'desc',
			cursor: null,
			limit: SEARCH_LIMIT
		} );

		items = disambiguateSubjectLabels( summaries.subjects.map( ( summary ) => ( {
			id: summary.id,
			label: summaryName( summary ),
			pageTitle: summary.pageTitle
		} ) ) ).map( subjectMenuItem );
	} catch ( error ) {
		failure = failureMessage( error, props.schemaName );
	}

	if ( sequence === searchSequence ) {
		menuItems.value = items;
		searchFailure.value = failure;
		searching.value = false;
	}
}
</script>
