<template>
	<div class="ext-neowiki-mappings-page">
		<CdxTable
			:columns="columns"
			:data="rows"
			:caption="$i18n( 'neowiki-special-mappings' ).text()"
			:pending="loading"
			:paginate="true"
			:server-pagination="true"
			:total-rows="totalRows"
			:pagination-size-default="paginationSizeOptions[ 0 ].value"
			:pagination-size-options="paginationSizeOptions"
			@load-more="onLoadMore"
		>
			<template #header>
				<CdxButton
					v-if="canCreateMappings"
					@click="isCreatorOpen = true"
				>
					<CdxIcon :icon="cdxIconAdd" />
					{{ $i18n( 'neowiki-mapping-creator-button' ).text() }}
				</CdxButton>
			</template>

			<template #item-name="{ item }">
				<a :href="mappingUrl( item )">{{ item }}</a>
			</template>

			<template #item-schemas="{ item }">
				<span
					v-if="item.length === 0"
					class="ext-neowiki-mappings-page__empty-value"
				>
					{{ $i18n( 'neowiki-mappings-schemas-none' ).text() }}
				</span>
				<template v-else>
					<span
						v-for="( schema, index ) in item"
						:key="schema"
					>
						<a :href="schemaUrl( schema )">{{ schema }}</a><span v-if="index < item.length - 1">, </span>
					</span>
					{{ schemaCountLabel( item.length ) }}
				</template>
			</template>

			<template #item-actions="{ row }">
				<span class="ext-neowiki-mappings-page__actions">
					<CdxButton
						v-if="canEditMapping"
						weight="quiet"
						:aria-label="$i18n( 'neowiki-edit-mapping' ).text()"
						@click="editMapping( row.name )"
					>
						<CdxIcon :icon="cdxIconEdit" />
					</CdxButton>
					<CdxButton
						v-if="canDeleteMapping"
						weight="quiet"
						action="destructive"
						:aria-label="$i18n( 'neowiki-mapping-delete' ).text()"
						@click="confirmDelete( row.name )"
					>
						<CdxIcon :icon="cdxIconTrash" />
					</CdxButton>
				</span>
			</template>

			<template #empty-state>
				{{ $i18n( 'neowiki-mappings-empty' ).text() }}
			</template>
		</CdxTable>

		<MappingCreatorDialog
			v-if="canCreateMappings"
			:open="isCreatorOpen"
			@update:open="isCreatorOpen = $event"
			@created="onMappingCreated"
		/>

		<DeletePageDialog
			:open="isDeleteConfirmOpen"
			:page-title="`Mapping:${ deletingMappingName }`"
			:display-name="deletingMappingName"
			:type-label="$i18n( 'neowiki-mapping-noun' ).text()"
			@update:open="isDeleteConfirmOpen = $event"
			@deleted="fetchMappings( lastOffset, pageSize )"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { CdxButton, CdxIcon, CdxTable } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconEdit, cdxIconTrash } from '@wikimedia/codex-icons';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { useCursorPagination } from '@/composables/useCursorPagination.ts';
import { useMappingPermissions } from '@/composables/useMappingPermissions.ts';
import MappingCreatorDialog from './MappingCreatorDialog.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';

const paginationSizeOptions: { value: number }[] = [
	{ value: 10 },
	{ value: 20 },
	{ value: 50 }
];

const loading = ref( true );
const pageSize = ref( paginationSizeOptions[ 0 ].value );
const lastOffset = ref( 0 );
const isCreatorOpen = ref( false );
// Undefined while the end of the listing is unknown, which keeps CdxTable in its indeterminate
// pagination. Once a response carries a null cursor the exact count is known, and the table needs
// it: its indeterminate next-button heuristic (a short page) misses a listing that ends exactly on
// a page boundary. The count covers only rows this client has itself paged through, so it reveals
// nothing the row listing did not already.
const totalRows = ref<number | undefined>( undefined );
const { cursorFor, recordNextCursor } = useCursorPagination();

const isDeleteConfirmOpen = ref( false );
const deletingMappingName = ref( '' );

const { canEditMapping, canDeleteMapping, canCreateMappings, checkEditPermission, checkDeletePermission, checkCreatePermission } = useMappingPermissions();

interface MappingRow {
	name: string;
	schemas: string[];
}

const rows = ref<MappingRow[]>( [] );

const columns: TableColumn[] = [
	{
		id: 'name',
		label: mw.msg( 'neowiki-mappings-column-name' )
	},
	{
		id: 'schemas',
		label: mw.msg( 'neowiki-mappings-column-schemas' )
	},
	{
		id: 'actions',
		label: '',
		textAlign: 'end'
	}
];

function mappingUrl( name: string ): string {
	return mw.util.getUrl( `Mapping:${ name }` );
}

function schemaUrl( name: string ): string {
	return mw.util.getUrl( `Schema:${ name }` );
}

function schemaCountLabel( count: number ): string {
	return mw.msg( 'neowiki-mappings-schema-count', String( count ) );
}

interface MappingSummary {
	name: string;
	schemas: string[];
}

async function fetchMappings( offset: number, limit: number ): Promise<void> {
	loading.value = true;
	pageSize.value = limit;
	lastOffset.value = offset;

	const cursor = cursorFor( offset );
	const cursorParam = cursor === null ? '' : `&cursor=${ encodeURIComponent( cursor ) }`;
	const restApiUrl = NeoWikiExtension.getInstance().getMediaWiki().util.wikiScript( 'rest' );
	const httpClient = NeoWikiExtension.getInstance().newHttpClient();

	const response = await httpClient.get(
		`${ restApiUrl }/neowiki/v0/mappings?limit=${ limit }${ cursorParam }`
	);

	if ( !response.ok ) {
		loading.value = false;
		return;
	}

	const result: { mappings: MappingSummary[]; nextCursor: string | null } = await response.json();

	rows.value = result.mappings.map( ( summary ) => ( {
		name: summary.name,
		schemas: summary.schemas
	} ) );

	recordNextCursor( offset, limit, result.nextCursor );
	totalRows.value = result.nextCursor === null ? offset + result.mappings.length : undefined;
	loading.value = false;
}

function onLoadMore( offset: number, limit: number ): void {
	fetchMappings( offset, limit );
}

// A new Mapping is created empty (a skeleton), so navigate to it for the user to fill in its JSON —
// unlike the Schema/Layout creators, whose form produces a complete page and so stay on the list.
function onMappingCreated( name: string ): void {
	window.location.href = mappingUrl( name );
}

// There is no mapping editor UI; editing is done on the page's raw-JSON edit view.
function editMapping( name: string ): void {
	window.location.href = mw.util.getUrl( `Mapping:${ name }`, { action: 'edit' } );
}

function confirmDelete( mappingName: string ): void {
	deletingMappingName.value = mappingName;
	isDeleteConfirmOpen.value = true;
}

onMounted( async () => {
	await checkCreatePermission();
	await checkEditPermission( '' );
	await checkDeletePermission( '' );
	await fetchMappings( 0, paginationSizeOptions[ 0 ].value );
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-mappings-page {
	max-width: 64rem;

	&__empty-value {
		color: @color-subtle;
		user-select: none;
	}

	&__actions {
		display: inline-flex;
		gap: @spacing-25;
	}
}
</style>
