<template>
	<div class="ext-neowiki-overview">
		<div
			v-if="canCreateSubjectPage && hasSchemas"
			class="ext-neowiki-overview__actions"
		>
			<CdxButton
				action="progressive"
				weight="primary"
				@click="openCreator( undefined )"
			>
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'neowiki-createsubject-button' ).text() }}
			</CdxButton>
		</div>

		<div class="ext-neowiki-overview__pages">
			<CdxCard
				v-for="link in pageLinks"
				:key="link.page"
				class="ext-neowiki-overview__page"
				:url="pageUrl( link.page )"
			>
				<template #title>
					{{ $i18n( link.label ).text() }}
				</template>
				<template #description>
					{{ $i18n( link.description ).text() }}
				</template>
			</CdxCard>
		</div>

		<CdxTable
			:columns="columns"
			:data="rows"
			:caption="$i18n( 'neowiki-special-schemas' ).text()"
			:pending="loading"
		>
			<template #header>
				<a :href="pageUrl( 'Special:Schemas' )">
					{{ $i18n( 'neowiki-overview-manage-schemas' ).text() }}
				</a>
			</template>

			<template #item-name="{ item }">
				<a :href="schemaUrl( item )">{{ item }}</a>
			</template>

			<template #item-actions="{ row }">
				<CdxButton
					v-if="canCreateSubjectPage"
					action="progressive"
					@click="openCreator( row.name )"
				>
					<CdxIcon :icon="cdxIconAdd" />
					{{ $i18n( 'neowiki-schema-create-subject', row.name ).text() }}
				</CdxButton>
			</template>

			<template #empty-state>
				{{ $i18n( 'neowiki-schemas-empty' ).text() }}
			</template>
		</CdxTable>

		<SubjectCreatorDialog
			v-if="canCreateSubjectPage"
			v-model:open="subjectStore.subjectCreatorOpen"
			:host-page="null"
			:initial-schema-name="pinnedSchema"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { CdxButton, CdxCard, CdxIcon, CdxTable } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import type { SchemaSummary } from '@/application/SchemaLookup.ts';

const PAGE_LINKS = [
	{
		page: 'Special:Layouts',
		label: 'neowiki-special-layouts',
		description: 'neowiki-overview-layouts-description'
	},
	{
		page: 'Special:Mappings',
		label: 'neowiki-special-mappings',
		description: 'neowiki-overview-mappings-description'
	}
];

const GRAPH_STORES_LINK = {
	page: 'Special:GraphStores',
	label: 'neowiki-special-graphstores',
	description: 'neowiki-overview-graph-stores-description'
};

const CONFIGURATION_LINK = {
	page: 'MediaWiki:NeoWiki',
	label: 'neowiki-overview-configuration',
	description: 'neowiki-overview-configuration-description'
};

const props = defineProps<{
	canManageGraphStores: boolean;
	canEditConfiguration: boolean;
}>();

const schemaStore = useSchemaStore();
const subjectStore = useSubjectStore();
const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();

const loading = ref( true );
const rows = ref<SchemaSummary[]>( [] );
// The Schema the creator opens on, which each button decides anew: the picker for the one above the
// list, the row's own Schema for the one in it.
const pinnedSchema = ref<string | undefined>( undefined );

const hasSchemas = computed( () => rows.value.length > 0 );

const pageLinks = computed( () => [
	...PAGE_LINKS,
	...( props.canManageGraphStores ? [ GRAPH_STORES_LINK ] : [] ),
	...( props.canEditConfiguration ? [ CONFIGURATION_LINK ] : [] )
] );

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
		id: 'actions',
		label: ''
	}
];

function schemaUrl( name: string ): string {
	return mw.util.getUrl( `Schema:${ name }` );
}

function pageUrl( page: string ): string {
	return mw.util.getUrl( page );
}

// The pin is set before the store is told, because the dialog reads it as it opens.
function openCreator( schemaName: string | undefined ): void {
	pinnedSchema.value = schemaName;
	subjectStore.openSubjectCreator();
}

onMounted( async () => {
	checkCreateSubjectPagePermission();

	try {
		rows.value = await schemaStore.fetchAllSchemaSummaries();
	} catch ( error ) {
		// Said out loud, because the table it leaves behind reads as a wiki without Schemas.
		mw.notify( error instanceof Error ? error.message : String( error ), { type: 'error' } );
	}

	loading.value = false;
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-overview {
	max-width: 64rem;

	// A block of its own: Codex sets the button's margin to zero after this stylesheet loads.
	&__actions {
		margin-bottom: @spacing-150;
	}

	&__pages {
		display: flex;
		flex-wrap: wrap;
		gap: @spacing-100;
		margin-bottom: @spacing-150;
	}

	&__page {
		flex: 1 1 14rem;
	}
}
</style>
