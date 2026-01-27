<template>
	<div class="ext-neowiki-edit-schema-action">
		<SchemaEditor
			ref="schemaEditor"
			:initial-schema="initialSchema as Schema"
		/>

		<EditSummary
			:help-text="$i18n( 'neowiki-edit-summary-help-text-schema' ).text()"
			:save-button-label="$i18n( 'neowiki-save-schema' ).text()"
			@save="handleSave"
		/>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor, { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import { Schema } from '@/domain/Schema.ts';
import { ref } from 'vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

defineProps<{ initialSchema: Schema }>();

const schemaEditor = ref<SchemaEditorExposes | null>( null );
const schemaStore = useSchemaStore();

const handleSave = async ( summary: string ): Promise<void> => {
	if ( !schemaEditor.value ) {
		return;
	}

	const schema = schemaEditor.value.getSchema();
	const schemaName = schema.getName();
	const editSummary = summary || 'Update schema via NeoWiki UI'; // TODO: i18n

	try {
		await schemaStore.saveSchema( schema, editSummary );
		// TODO: i18n
		mw.notify( `Updated ${ schemaName } schema`, { type: 'success' } );
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				// TODO: i18n
				title: `Failed to update ${ schemaName } schema.`,
				type: 'error'
			}
		);
	}
};
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-edit-schema-action {
	border: @border-base;
	border-radius: @border-radius-base;

	.ext-neowiki-edit-summary {
		border-block-start: @border-subtle;
		padding: @spacing-100;

		@media ( min-width: @min-width-breakpoint-desktop ) {
			padding: @spacing-150;
		}
	}
}
</style>
