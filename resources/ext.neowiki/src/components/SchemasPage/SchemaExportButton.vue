<template>
	<CdxButton
		:disabled="exporting"
		@click="exportAllSchemas"
	>
		<CdxIcon :icon="cdxIconDownload" />
		{{ $i18n( 'neowiki-schemas-export-button' ).text() }}
	</CdxButton>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconDownload } from '@wikimedia/codex-icons';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { exportSchemas } from '@/components/SchemasPage/schemaExport.ts';
import { downloadFile } from '@/presentation/downloadFile.ts';

const schemaStore = useSchemaStore();
const schemaLookup = NeoWikiServices.getSchemaRepository();
const exporting = ref( false );

async function exportAllSchemas(): Promise<void> {
	exporting.value = true;

	try {
		const summaries = await schemaStore.fetchAllSchemaSummaries();
		const file = await exportSchemas( summaries.map( ( summary ) => summary.name ), schemaLookup );
		downloadFile( `${ mw.config.get( 'wgServerName' ) }-schemas.json`, file, 'application/json' );
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-schemas-export-error' ),
				type: 'error'
			}
		);
	} finally {
		exporting.value = false;
	}
}
</script>
