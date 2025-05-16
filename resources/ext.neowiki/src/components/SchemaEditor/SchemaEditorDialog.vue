<template>
	<div>
		<CdxDialog title="TODO">
			<SchemaEditor
				ref="schemaEditor"
				:initial-schema="initialSchema"
			/>

			<template #footer>
				<EditSummary
					:help-text="$i18n( 'neowiki-edit-summary-help-text-schema' ).text()"
					:save-button-label="$i18n( 'neowiki-save-schema' ).text()"
					@save="saveSchema"
				/>
			</template>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor, { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import { CdxDialog } from '@wikimedia/codex';
import { Schema } from '@neo/domain/Schema.ts';
import { ref } from 'vue';

defineProps<{ initialSchema: Schema }>();

const schemaEditor = ref<SchemaEditorExposes | null>( null );

const saveSchema = async (): Promise<void> => {
	console.log( 'TODO: saving schema', schemaEditor.value!.getSchema() );
	// TODO: emit event
};
</script>
