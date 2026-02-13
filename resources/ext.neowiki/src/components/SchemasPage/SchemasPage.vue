<template>
	<div class="ext-neowiki-schemas-page">
		<CdxTable
			:columns="columns"
			:data="rows"
			:caption="$i18n( 'neowiki-special-schemas' ).text()"
			:hide-caption="true"
			:pending="loading"
		>
			<template #item-name="{ item }">
				<a :href="schemaUrl( item )">{{ item }}</a>
			</template>

			<template #empty-state>
				{{ $i18n( 'neowiki-schemas-empty' ).text() }}
			</template>
		</CdxTable>
	</div>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { CdxTable } from '@wikimedia/codex';
import type { TableColumn } from '@wikimedia/codex';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

const loading = ref( true );

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

onMounted( async () => {
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
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schemas-page {
	max-width: 64rem;
}
</style>
