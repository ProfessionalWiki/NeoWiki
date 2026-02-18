<template>
	<div class="ext-neowiki-schemas-page">
		<CdxTable
			:columns="columns"
			:data="rows"
			:caption="$i18n( 'neowiki-special-schemas' ).text()"
			:pending="loading"
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

			<template #empty-state>
				{{ $i18n( 'neowiki-schemas-empty' ).text() }}
			</template>
		</CdxTable>

		<SchemaCreatorDialog
			v-if="canCreateSchemas"
			:open="isCreatorOpen"
			@update:open="isCreatorOpen = $event"
			@created="fetchSchemas"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { CdxButton, CdxIcon, CdxTable } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import SchemaCreatorDialog from './SchemaCreatorDialog.vue';

const loading = ref( true );
const isCreatorOpen = ref( false );

const { canCreateSchemas, checkCreatePermission } = useSchemaPermissions();

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
	}
];

function schemaUrl( name: string ): string {
	return mw.util.getUrl( `Schema:${ name }` );
}

interface SchemaSummary {
	name: string;
	description: string;
	propertyCount: number;
}

async function fetchSchemas(): Promise<void> {
	loading.value = true;

	const restApiUrl = NeoWikiExtension.getInstance().getMediaWiki().util.wikiScript( 'rest' );
	const httpClient = NeoWikiExtension.getInstance().newHttpClient();

	const response = await httpClient.get( `${ restApiUrl }/neowiki/v0/schemas` );

	if ( !response.ok ) {
		loading.value = false;
		return;
	}

	const summaries: SchemaSummary[] = await response.json();

	rows.value = summaries.map( ( summary ) => ( {
		name: summary.name,
		description: summary.description,
		properties: summary.propertyCount
	} ) );

	loading.value = false;
}

onMounted( async () => {
	await checkCreatePermission();
	await fetchSchemas();
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
}
</style>
