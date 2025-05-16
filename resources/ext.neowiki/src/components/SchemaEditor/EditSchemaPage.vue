<template>
	<div class="ext-neowiki-edit-schema-action">
		<SchemaEditor
			ref="schemaEditor"
			:initial-schema="initialSchema as Schema"
		/>

		<EditSummary
			:help-text="$i18n( 'neowiki-edit-summary-help-text-schema' ).text()"
			:save-button-label="$i18n( 'neowiki-save-schema' ).text()"
			@save="saveSchema"
		/>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor, { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import { Schema } from '@neo/domain/Schema.ts';
import { ref } from 'vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

defineProps<{ initialSchema: Schema }>();

const schemaEditor = ref<SchemaEditorExposes | null>( null );

const schemaRepository = NeoWikiServices.getSchemaRepository();

const saveSchema = async (): Promise<void> => {
	const schema = schemaEditor.value!.getSchema();

	try {
		await schemaRepository.saveSchema( schema );
		mw.notify(
			'TODO: No edit summary provided.',
			{
				title: `Updated ${ schema.getName() } schema`,
				type: 'success'
			}
		);
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: `Failed to update ${ schema.getName() } schema.`,
				type: 'error'
			}
		);
	}
};
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.ext-neowiki-edit-schema-action {
	border: $border-base;
	border-radius: $border-radius-base;

	.ext-neowiki-edit-summary {
		border-block-start: $border-subtle;
		padding: $spacing-100;

		@media ( min-width: $min-width-breakpoint-desktop ) {
			padding: $spacing-150;
		}
	}
}
</style>
