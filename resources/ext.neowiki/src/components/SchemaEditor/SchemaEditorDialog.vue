<template>
	<div>
		<CdxDialog title="TODO">
			<SchemaEditor
				ref="schemaEditor"
				:initial-schema="initialSchema"
			/>

			<CdxTextArea />

			<CdxButton
				action="progressive"
				weight="primary"
				@click="saveSchema"
			>
				<CdxIcon :icon="cdxIconCheck" />
				{{ $i18n( 'neowiki-save-schema' ).text() }}
			</CdxButton>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor, { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import { CdxButton, CdxDialog, CdxIcon, CdxTextArea } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';
import { Schema } from '@neo/domain/Schema.ts';
import { ref } from 'vue';

defineProps<{ initialSchema: Schema }>();

const schemaEditor = ref<SchemaEditorExposes | null>( null );

const saveSchema = async (): Promise<void> => {
	console.log( 'TODO: saving schema', schemaEditor.value!.getSchema() );
	// TODO: emit event
};
</script>
