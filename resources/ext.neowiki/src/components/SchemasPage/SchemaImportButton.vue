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
			v-if="bundle !== null"
			:bundle="bundle"
			@imported="emit( 'imported' )"
			@close="bundle = null"
		/>
	</span>
</template>

<script setup lang="ts">
import { ref, shallowRef } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconUpload } from '@wikimedia/codex-icons';
import SchemaImportDialog from '@/components/SchemasPage/SchemaImportDialog.vue';
import { parseBundleFile } from '@/persistence/bundleFile.ts';
import type { ModelBundle } from '@/application/modelBundle/ModelBundle.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const emit = defineEmits<{
	imported: [];
}>();

const permissionHints = NeoWikiServices.getModelBundlePermissionHints();
const fileInput = ref<HTMLInputElement | null>( null );
const bundle = shallowRef<ModelBundle | null>( null );

/**
 * A file with a Model bundle's metadata installs that bundle, which takes the right to write its install
 * record; any other imports its items as they are.
 */
async function onFileChosen( event: Event ): Promise<void> {
	const input = event.target as HTMLInputElement;
	const file = input.files?.[ 0 ];

	// Cleared so that choosing the same file again still fires a change event.
	input.value = '';

	if ( file === undefined ) {
		return;
	}

	try {
		const chosen = parseBundleFile( await file.text() );

		if ( chosen.metadata !== null && !await permissionHints.canInstallModelBundles() ) {
			throw new Error( mw.msg( 'neowiki-bundle-install-not-allowed' ) );
		}

		bundle.value = chosen;
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
