<template>
	<span>
		<CdxButton @click="fileInput?.click()">
			<CdxIcon :icon="cdxIconUpload" />
			{{ $i18n( 'neowiki-schemas-import-button' ).text() }}
		</CdxButton>
		<input
			ref="fileInput"
			type="file"
			accept=".json,application/json"
			hidden
			@change="onFileChosen"
		>
		<SchemaImportDialog
			v-if="items !== null"
			:items="items"
			@imported="emit( 'imported' )"
			@close="items = null"
		/>
	</span>
</template>

<script setup lang="ts">
import { ref, shallowRef } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconUpload } from '@wikimedia/codex-icons';
import SchemaImportDialog from '@/components/SchemasPage/SchemaImportDialog.vue';
import { parseSchemaExport } from '@/components/SchemasPage/schemaExport.ts';
import { planSchemaImport, type SchemaImportItem } from '@/components/SchemasPage/schemaImport.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

const emit = defineEmits<{
	imported: [];
}>();

const schemaStore = useSchemaStore();
const fileInput = ref<HTMLInputElement | null>( null );
const items = shallowRef<SchemaImportItem[] | null>( null );

async function onFileChosen( event: Event ): Promise<void> {
	const input = event.target as HTMLInputElement;
	const file = input.files?.[ 0 ];

	// Cleared so that choosing the same file again still fires a change event.
	input.value = '';

	if ( file === undefined ) {
		return;
	}

	try {
		const schemas = parseSchemaExport( await file.text() );
		const summaries = await schemaStore.fetchAllSchemaSummaries();
		items.value = planSchemaImport( schemas, summaries.map( ( summary ) => summary.name ) );
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-schemas-import-error' ),
				type: 'error'
			}
		);
	}
}
</script>
