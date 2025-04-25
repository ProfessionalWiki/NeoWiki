<template>
	<div>
		<CdxDialog title="TODO">
			<SchemaEditor
				:schema="schema"
				@update:schema="handleSchemaUpdate"
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
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import { CdxButton, CdxDialog, CdxIcon, CdxTextArea } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';
import { Schema } from '@neo/domain/Schema.ts';

defineProps<{ schema: Schema }>();

const handleSchemaUpdate = ( updatedSchema: Schema ): void => {
	console.log( 'Schema updated', updatedSchema );
};

const saveSchema = async (): Promise<void> => {
	// TODO: emit event
};
</script>
