<template>
	<div class="ext-neowiki-layouts-page">
		<CdxTable
			:columns="columns"
			:data="rows"
			:caption="$i18n( 'neowiki-special-layouts' ).text()"
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
					v-if="canCreateLayouts"
					@click="isCreatorOpen = true"
				>
					<CdxIcon :icon="cdxIconAdd" />
					{{ $i18n( 'neowiki-layout-creator-button' ).text() }}
				</CdxButton>
			</template>

			<template #item-name="{ item }">
				<a :href="layoutUrl( item )">{{ item }}</a>
			</template>

			<template #item-description="{ item }">
				<span
					v-if="!item"
					class="ext-neowiki-layouts-page__empty-value"
				>-</span>
				<template v-else>
					{{ item }}
				</template>
			</template>

			<template #item-schema="{ item }">
				<a :href="schemaUrl( item )">{{ item }}</a>
			</template>

			<template #item-actions="{ row }">
				<span
					v-if="canEditLayouts"
					class="ext-neowiki-layouts-page__actions"
				>
					<CdxButton
						weight="quiet"
						:aria-label="$i18n( 'neowiki-edit-layout' ).text()"
						@click="openEditor( row.name )"
					>
						<CdxIcon :icon="cdxIconEdit" />
					</CdxButton>
					<CdxButton
						weight="quiet"
						action="destructive"
						:aria-label="$i18n( 'neowiki-layout-delete' ).text()"
						@click="confirmDelete( row.name )"
					>
						<CdxIcon :icon="cdxIconTrash" />
					</CdxButton>
				</span>
			</template>

			<template #empty-state>
				{{ $i18n( 'neowiki-layouts-empty' ).text() }}
			</template>
		</CdxTable>

		<LayoutCreatorDialog
			v-if="canCreateLayouts"
			:open="isCreatorOpen"
			:on-save="handleCreateLayout"
			@update:open="isCreatorOpen = $event"
			@created="fetchLayouts( 0, pageSize )"
		/>

		<LayoutEditorDialog
			v-if="canEditLayouts && editingLayout !== null"
			:open="isEditorOpen"
			:initial-layout="editingLayout"
			:on-save="handleSaveLayout"
			@saved="onLayoutSaved"
			@update:open="onEditorOpenChange"
		/>

		<DeletePageDialog
			:open="isDeleteConfirmOpen"
			:page-title="`Layout:${ deletingLayoutName }`"
			:display-name="deletingLayoutName"
			:type-label="$i18n( 'neowiki-layout-noun' ).text()"
			@update:open="isDeleteConfirmOpen = $event"
			@deleted="fetchLayouts( lastOffset, pageSize )"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, shallowRef, onMounted, nextTick } from 'vue';
import { CdxButton, CdxIcon, CdxTable } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconEdit, cdxIconTrash } from '@wikimedia/codex-icons';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { useLayoutPermissions } from '@/composables/useLayoutPermissions.ts';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { Layout } from '@/domain/Layout.ts';
import LayoutCreatorDialog from './LayoutCreatorDialog.vue';
import LayoutEditorDialog from '@/components/LayoutEditor/LayoutEditorDialog.vue';
import DeletePageDialog from '@/components/common/DeletePageDialog.vue';

const paginationSizeOptions: { value: number }[] = [
	{ value: 10 },
	{ value: 20 },
	{ value: 50 }
];

const loading = ref( true );
const totalRows = ref( 0 );
const isCreatorOpen = ref( false );
const pageSize = ref( paginationSizeOptions[ 0 ].value );
const lastOffset = ref( 0 );
const { canCreateLayouts, canEditLayout: canEditLayouts, checkCreatePermission, checkEditPermission } = useLayoutPermissions();
const layoutStore = useLayoutStore();

const isEditorOpen = ref( false );
const editingLayout = shallowRef<Layout | null>( null );

const isDeleteConfirmOpen = ref( false );
const deletingLayoutName = ref( '' );

interface LayoutRow {
	name: string;
	schema: string;
	type: string;
	description: string;
}

const rows = ref<LayoutRow[]>( [] );

const columns: TableColumn[] = [
	{
		id: 'name',
		label: mw.msg( 'neowiki-layouts-column-name' )
	},
	{
		id: 'schema',
		label: mw.msg( 'neowiki-layouts-column-schema' )
	},
	{
		id: 'type',
		label: mw.msg( 'neowiki-layouts-column-type' )
	},
	{
		id: 'description',
		label: mw.msg( 'neowiki-layouts-column-description' )
	},
	{
		id: 'actions',
		label: ''
	}
];

function layoutUrl( name: string ): string {
	return mw.util.getUrl( `Layout:${ name }` );
}

function schemaUrl( name: string ): string {
	return mw.util.getUrl( `Schema:${ name }` );
}

interface LayoutSummary {
	name: string;
	schema: string;
	type: string;
	description: string;
	ruleCount: number;
}

async function fetchLayouts( offset: number, limit: number ): Promise<void> {
	loading.value = true;
	pageSize.value = limit;
	lastOffset.value = offset;

	const restApiUrl = NeoWikiExtension.getInstance().getMediaWiki().util.wikiScript( 'rest' );
	const httpClient = NeoWikiExtension.getInstance().newHttpClient();

	const response = await httpClient.get(
		`${ restApiUrl }/neowiki/v0/layouts?limit=${ limit }&offset=${ offset }`
	);

	if ( !response.ok ) {
		loading.value = false;
		return;
	}

	const result: { layouts: LayoutSummary[]; totalRows: number } = await response.json();

	rows.value = result.layouts.map( ( summary ) => ( {
		name: summary.name,
		schema: summary.schema,
		type: summary.type,
		description: summary.description
	} ) );

	totalRows.value = result.totalRows;
	loading.value = false;
}

function onLoadMore( offset: number, limit: number ): void {
	fetchLayouts( offset, limit );
}

async function openEditor( layoutName: string ): Promise<void> {
	try {
		editingLayout.value = null;
		await nextTick();

		await Promise.all( [
			layoutStore.fetchLayout( layoutName ),
			fetchLayouts( lastOffset.value, pageSize.value )
		] );
		editingLayout.value = layoutStore.getLayout( layoutName ) ?? null;
		isEditorOpen.value = true;
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{ type: 'error' }
		);
	}
}

const handleSaveLayout = async ( updatedLayout: Layout, comment: string ): Promise<void> => {
	await layoutStore.saveLayout( updatedLayout, comment );
};

const handleCreateLayout = async ( layout: Layout, comment: string ): Promise<void> => {
	await layoutStore.saveLayout( layout, comment );
};

function onLayoutSaved(): void {
	fetchLayouts( lastOffset.value, pageSize.value );
}

function onEditorOpenChange( value: boolean ): void {
	isEditorOpen.value = value;
	if ( !value ) {
		editingLayout.value = null;
	}
}

function confirmDelete( layoutName: string ): void {
	deletingLayoutName.value = layoutName;
	isDeleteConfirmOpen.value = true;
}

onMounted( async () => {
	await checkCreatePermission();
	await checkEditPermission( '' );
	await fetchLayouts( 0, paginationSizeOptions[ 0 ].value );
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-layouts-page {
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
