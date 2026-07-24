<template>
	<div class="ext-neowiki-schemas-page">
		<CdxTable
			:columns="columns"
			:data="rows"
			:caption="$i18n( 'neowiki-special-schemas' ).text()"
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
					v-if="canCreateSchemas"
					@click="isCreatorOpen = true"
				>
					<CdxIcon :icon="cdxIconAdd" />
					{{ $i18n( 'neowiki-schema-creator-button' ).text() }}
				</CdxButton>
			</template>

			<template #item-name="{ item }">
				<a :href="schemaUrl( item )">{{ item }}</a>
			</template>

			<template #item-description="{ item }">
				<span
					v-if="!item"
					class="ext-neowiki-schemas-page__empty-value"
				>-</span>
				<template v-else>
					{{ item }}
				</template>
			</template>

			<template #item-actions="{ row }">
				<span
					v-if="canEditSchema"
					class="ext-neowiki-schemas-page__actions"
				>
					<CdxButton
						weight="quiet"
						:aria-label="$i18n( 'neowiki-edit-schema' ).text()"
						@click="openEditor( row.name )"
					>
						<CdxIcon :icon="cdxIconEdit" />
					</CdxButton>
					<CdxButton
						weight="quiet"
						action="destructive"
						:aria-label="$i18n( 'neowiki-schema-delete' ).text()"
						@click="confirmDelete( row.name )"
					>
						<CdxIcon :icon="cdxIconTrash" />
					</CdxButton>
				</span>
			</template>

			<template #empty-state>
				{{ $i18n( 'neowiki-schemas-empty' ).text() }}
			</template>
		</CdxTable>

		<SchemaCreatorDialog
			v-if="canCreateSchemas"
			:open="isCreatorOpen"
			@update:open="isCreatorOpen = $event"
			@created="fetchSchemas( 0, pageSize )"
		/>

		<SchemaEditorDialog
			v-if="canEditSchema && editingSchema !== null"
			:open="isEditorOpen"
			:initial-schema="editingSchema"
			:on-save="handleSaveSchema"
			@saved="onSchemaSaved"
			@update:open="onEditorOpenChange"
		/>

		<DeletePageDialog
			:open="isDeleteConfirmOpen"
			:page-title="`Schema:${ deletingSchemaName }`"
			:display-name="deletingSchemaName"
			:type-label="$i18n( 'neowiki-schema-noun' ).text()"
			@update:open="isDeleteConfirmOpen = $event"
			@deleted="fetchSchemas( lastOffset, pageSize )"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, shallowRef, onMounted, nextTick } from 'vue';
import { CdxButton, CdxIcon, CdxTable } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconEdit, cdxIconTrash } from '@wikimedia/codex-icons';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { useCursorPagination } from '@/composables/useCursorPagination.ts';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@/domain/Schema.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';
import SchemaCreatorDialog from './SchemaCreatorDialog.vue';
import SchemaEditorDialog from '@/components/SchemaEditor/SchemaEditorDialog.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';

const paginationSizeOptions: { value: number }[] = [
	{ value: 10 },
	{ value: 20 },
	{ value: 50 }
];

const loading = ref( true );
const isCreatorOpen = ref( false );
const pageSize = ref( paginationSizeOptions[ 0 ].value );
const lastOffset = ref( 0 );
// Undefined while the end of the listing is unknown, which keeps CdxTable in its indeterminate
// pagination. Once a response carries a null cursor the exact count is known, and the table needs
// it: its indeterminate next-button heuristic (a short page) misses a listing that ends exactly on
// a page boundary. The count covers only rows this client has itself paged through, so it reveals
// nothing the row listing did not already.
const totalRows = ref<number | undefined>( undefined );
const { cursorFor, recordNextCursor } = useCursorPagination();
const { canEditSchema, canCreateSchemas, checkEditPermission, checkCreatePermission } = useSchemaPermissions();
const schemaStore = useSchemaStore();

const isEditorOpen = ref( false );
const editingSchema = shallowRef<Schema | null>( null );

const isDeleteConfirmOpen = ref( false );
const deletingSchemaName = ref( '' );

interface SchemaRow {
	name: string;
	description: string;
	properties: number;
}

const rows = ref<SchemaRow[]>( [] );

const columns: TableColumn[] = [
	{
		id: 'name',
		label: mw.msg( 'neowiki-schemas-column-name' )
	},
	{
		id: 'description',
		label: mw.msg( 'neowiki-schemas-column-description' )
	},
	{
		id: 'properties',
		label: mw.msg( 'neowiki-schemas-column-properties' )
	},
	{
		id: 'actions',
		label: ''
	}
];

function schemaUrl( name: string ): string {
	return mw.util.getUrl( `Schema:${ name }` );
}

async function fetchSchemas( offset: number, limit: number ): Promise<void> {
	loading.value = true;
	pageSize.value = limit;
	lastOffset.value = offset;

	const cursor = cursorFor( offset );
	const cursorParam = cursor === null ? '' : `&cursor=${ encodeURIComponent( cursor ) }`;
	const restApiUrl = NeoWikiExtension.getInstance().getMediaWiki().util.wikiScript( 'rest' );
	const httpClient = NeoWikiExtension.getInstance().newHttpClient();

	const response = await httpClient.get(
		`${ restApiUrl }/neowiki/v0/schemas?limit=${ limit }${ cursorParam }`
	);

	if ( !response.ok ) {
		loading.value = false;
		return;
	}

	const result: { schemas: SchemaSummary[]; nextCursor: string | null } = await response.json();

	rows.value = result.schemas.map( ( summary ) => ( {
		name: summary.name,
		description: summary.description,
		properties: summary.propertyCount
	} ) );

	recordNextCursor( offset, limit, result.nextCursor );
	totalRows.value = result.nextCursor === null ? offset + result.schemas.length : undefined;
	loading.value = false;
}

function onLoadMore( offset: number, limit: number ): void {
	fetchSchemas( offset, limit );
}

async function openEditor( schemaName: string ): Promise<void> {
	try {
		editingSchema.value = null;
		await nextTick();

		await Promise.all( [
			schemaStore.fetchSchema( schemaName ),
			fetchSchemas( lastOffset.value, pageSize.value )
		] );
		editingSchema.value = schemaStore.getSchema( schemaName ) ?? null;
		isEditorOpen.value = true;
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{ type: 'error' }
		);
	}
}

const handleSaveSchema = async ( updatedSchema: Schema, comment: string ): Promise<void> => {
	await schemaStore.saveSchema( updatedSchema, comment );
};

function onSchemaSaved(): void {
	fetchSchemas( lastOffset.value, pageSize.value );
}

function onEditorOpenChange( value: boolean ): void {
	isEditorOpen.value = value;
	if ( !value ) {
		editingSchema.value = null;
	}
}

function confirmDelete( schemaName: string ): void {
	deletingSchemaName.value = schemaName;
	isDeleteConfirmOpen.value = true;
}

onMounted( async () => {
	await checkCreatePermission();
	await checkEditPermission( '' );
	await fetchSchemas( 0, paginationSizeOptions[ 0 ].value );
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schemas-page {
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
