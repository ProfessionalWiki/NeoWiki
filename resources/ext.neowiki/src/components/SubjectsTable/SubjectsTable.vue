<template>
	<div
		class="ext-neowiki-subjects-table"
		:class="{ 'ext-neowiki-subjects-table--hidden-caption': fixedSchema === undefined }"
	>
		<CdxTable
			ref="table"
			v-model:sort="sort"
			:columns="columns"
			:data="rows"
			:caption="$i18n( 'neowiki-special-subjects' ).text()"
			:hide-caption="fixedSchema === undefined"
			:pending="loading"
			:paginate="true"
			:server-pagination="true"
			:total-rows="totalRows"
			:pagination-size-default="PAGE_SIZES[ 0 ].value"
			:pagination-size-options="PAGE_SIZES"
			@load-more="fetchPage"
		>
			<template #header>
				<div class="ext-neowiki-subjects-table__controls">
					<CdxSelect
						v-if="fixedSchema === undefined"
						v-model:selected="schemaChoice"
						:menu-items="schemaMenuItems"
						:aria-label="$i18n( 'neowiki-subjects-schema-label' ).text()"
					/>
					<CdxSearchInput
						v-model="searchText"
						class="ext-neowiki-subjects-table__search"
						:placeholder="$i18n( 'neowiki-subjects-search-placeholder' ).text()"
						:aria-label="$i18n( 'neowiki-subjects-search-placeholder' ).text()"
					/>
					<CdxButton
						v-if="fixedSchema === undefined && canCreate"
						class="ext-neowiki-subjects-table__create"
						@click="emit( 'create', selectedSchema ?? undefined )"
					>
						<CdxIcon :icon="cdxIconAdd" />
						{{ createLabel }}
					</CdxButton>
				</div>
			</template>

			<template #item-name="{ row }">
				<SubjectSummaryCell
					column="name"
					:summary="row"
				/>
			</template>

			<template #item-id="{ row }">
				<SubjectSummaryCell
					column="id"
					:summary="row"
				/>
			</template>

			<template #item-schema="{ row }">
				<SubjectSummaryCell
					column="schema"
					:summary="row"
				/>
			</template>

			<template #item-page="{ row }">
				<SubjectSummaryCell
					column="page"
					:summary="row"
				/>
			</template>

			<template #item-edited="{ row }">
				<SubjectSummaryCell
					column="edited"
					:summary="row"
				/>
			</template>

			<!-- Codex shows the empty state whenever the table has no rows, so also while the first ones load. -->
			<template
				v-if="!loading"
				#empty-state
			>
				{{ emptyText }}
			</template>
		</CdxTable>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, onScopeDispose, ref, watch } from 'vue';
import { CdxButton, CdxIcon, CdxSearchInput, CdxSelect, CdxTable } from '@wikimedia/codex';
import type { MenuItemData, TableSort } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import SubjectSummaryCell from './SubjectSummaryCell.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useCursorPagination } from '@/composables/useCursorPagination.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { isSubjectFirst } from '@/wikiMode.ts';
import type { SubjectSummary } from '@/application/SubjectSummaryLookup.ts';
import { emptyStateMessage, schemaMenuNames, subjectColumns, summaryOrder } from '@/presentation/subjectSummaryTable.ts';

const props = withDefaults( defineProps<{
	/** Lists only this Schema's Subjects, with no menu to pick another: a Schema's own page. */
	fixedSchema?: string;
	/** The Schema the menu starts on; null starts on every Schema. */
	initialSchema?: string | null;
	canCreate?: boolean;
}>(), {
	fixedSchema: undefined,
	initialSchema: null,
	canCreate: false
} );

const emit = defineEmits<{
	'update:schema': [ schema: string | null ];
	create: [ schema: string | undefined ];
}>();

const SEARCH_DELAY_MS = 300;
const PAGE_SIZES = [ { value: 10 }, { value: 20 }, { value: 50 } ];
// CdxSelect needs a value for the "every Schema" item; no Schema has an empty name.
const ALL_SCHEMAS = '';

const lookup = NeoWikiServices.getSubjectSummaryLookup();
const schemaStore = useSchemaStore();
const pagination = useCursorPagination();

const table = ref<InstanceType<typeof CdxTable> | null>( null );
const rows = ref<SubjectSummary[]>( [] );
const loading = ref( true );
const loadFailed = ref( false );
// Undefined while the end of the listing is unknown, which keeps the pager at "of many" (ADR 27).
const totalRows = ref<number | undefined>( undefined );
const sort = ref<TableSort>( {} );
const selectedSchema = ref<string | null>( props.fixedSchema ?? props.initialSchema );
const searchText = ref( '' );
const appliedSearch = ref( '' );
const schemaNames = ref<string[]>( [] );
let searchTimer: ReturnType<typeof setTimeout> | null = null;
let requestSequence = 0;

const columns = computed( () => subjectColumns( {
	showSchema: selectedSchema.value === null,
	showPage: !isSubjectFirst(),
	sortable: true
} ) );

const schemaChoice = computed( {
	get: (): string => selectedSchema.value ?? ALL_SCHEMAS,
	set: ( value: string ): void => {
		selectedSchema.value = value === ALL_SCHEMAS ? null : value;
	}
} );

const schemaMenuItems = computed( (): MenuItemData[] => [
	{ value: ALL_SCHEMAS, label: mw.msg( 'neowiki-subjects-all-schemas' ) },
	...schemaMenuNames( schemaNames.value, selectedSchema.value ).map( ( name ) => ( { value: name, label: name } ) )
] );

const createLabel = computed( () => selectedSchema.value === null ?
	mw.msg( 'neowiki-createsubject-button' ) :
	mw.msg( 'neowiki-schema-create-subject', selectedSchema.value ) );

const emptyText = computed( () => loadFailed.value ?
	mw.msg( 'neowiki-subjects-load-error' ) :
	emptyStateMessage( appliedSearch.value, selectedSchema.value ) );

async function fetchPage( offset: number, limit: number ): Promise<void> {
	// Rows no response led to, as Previous asks for after a page-size change: nothing resumes the listing there.
	if ( !pagination.hasCursorFor( offset ) ) {
		restart();
		return;
	}

	const sequence = ++requestSequence;
	loading.value = true;

	try {
		const result = await lookup.getSubjectSummaries( {
			schema: selectedSchema.value,
			search: appliedSearch.value,
			...summaryOrder( sort.value ),
			cursor: pagination.cursorFor( offset ),
			limit
		} );

		if ( sequence !== requestSequence ) {
			return;
		}

		rows.value = result.subjects;
		loadFailed.value = false;
		pagination.recordNextCursor( offset, limit, result.nextCursor );
		totalRows.value = result.nextCursor === null ? offset + result.subjects.length : undefined;
	} catch {
		if ( sequence !== requestSequence ) {
			return;
		}

		rows.value = [];
		loadFailed.value = true;
	}

	loading.value = false;
}

/**
 * Lists from the first page again. CdxTable keeps its page offset to itself, so the table is sent to its first page
 * by its own handler, undocumented but typed, which asks for that page through load-more. Remounting the table would
 * reset it too, but would take the keyboard focus from the search field or sort button the user just used.
 */
function restart(): void {
	pagination.reset();
	totalRows.value = undefined;
	table.value?.onFirst();
}

function clearSearchTimer(): void {
	if ( searchTimer !== null ) {
		clearTimeout( searchTimer );
		searchTimer = null;
	}
}

watch( searchText, ( text ) => {
	clearSearchTimer();
	searchTimer = setTimeout( () => {
		appliedSearch.value = text.trim();
	}, SEARCH_DELAY_MS );
} );

// A sort on the Schema column means nothing once one Schema is chosen, so a new Schema starts unsorted.
watch( selectedSchema, ( schema ) => {
	sort.value = {};
	emit( 'update:schema', schema );
} );

watch( [ selectedSchema, appliedSearch, sort ], restart );

onScopeDispose( clearSearchTimer );

onMounted( async () => {
	fetchPage( 0, PAGE_SIZES[ 0 ].value );

	if ( props.fixedSchema !== undefined ) {
		return;
	}

	try {
		schemaNames.value = ( await schemaStore.fetchAllSchemaSummaries() ).map( ( summary ) => summary.name );
	} catch ( error ) {
		mw.notify( error instanceof Error ? error.message : String( error ), { type: 'error' } );
	}
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subjects-table {
	// Required to start the controls at the inline-start of the table header
	&--hidden-caption {
		.cdx-table__header__caption {
			display: none;
		}

		.cdx-table__header__content {
			flex-grow: 1;
		}
	}

	&__controls {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-50;
		align-items: center;
	}

	&__search {
		min-width: 16rem;
	}

	// Scoped under the controls to outrank `.cdx-button`'s margin, which MediaWiki's Codex loads after this.
	&__controls &__create {
		margin-inline-start: auto;
	}
}
</style>
