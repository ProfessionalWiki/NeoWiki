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
import { useSchemaSaver } from '@/composables/useSchemaSaver.ts';

defineProps<{ initialSchema: Schema }>();

const schemaEditor = ref<SchemaEditorExposes | null>( null );
const { saveSchema } = useSchemaSaver();

const handleSave = async ( summary: string ): Promise<void> => {
	if ( !schemaEditor.value ) {
		return;
	}
	await saveSchema( schemaEditor.value.getSchema(), summary );
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
