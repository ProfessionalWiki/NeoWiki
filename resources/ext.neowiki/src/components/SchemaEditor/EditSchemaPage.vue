<template>
	<div class="ext-neowiki-edit-schema-action">
		<SchemaEditor
			ref="schemaEditor"
			:initial-schema="initialSchema as Schema"
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
	</div>
</template>

<script setup lang="ts">
import SchemaEditor, { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import { CdxButton, CdxIcon, CdxTextArea } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';
import { Schema } from '@neo/domain/Schema.ts';
import { ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

defineProps<{ initialSchema: Schema }>();

const schemaEditor = ref<SchemaEditorExposes | null>( null );

const schemaRepository = NeoWikiServices.getSchemaRepository();

const saveSchema = async (): Promise<void> => {
	await schemaRepository.saveSchema( schemaEditor.value!.getSchema() )
};
</script>
