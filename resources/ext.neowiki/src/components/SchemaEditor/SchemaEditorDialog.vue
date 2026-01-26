<template>
	<div>
		<CdxDialog
			v-model:open="open"
			:use-close-button="true"
			class="ext-neowiki-schema-editor-dialog"
			:class="{ 'cdx-dialog--dividers': hasOverflow }"
			:title="$i18n( 'neowiki-editing-schema', props.initialSchema.getName() ).text()"
		>
			<SchemaEditor
				ref="schemaEditor"
				:initial-schema="initialSchema"
				@overflow="onOverflow"
			/>

			<template #footer>
				<EditSummary
					:help-text="$i18n( 'neowiki-edit-summary-help-text-schema' ).text()"
					:save-button-label="$i18n( 'neowiki-save-schema' ).text()"
					@save="handleSave"
				/>
			</template>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor, { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import { CdxDialog } from '@wikimedia/codex';
import { Schema } from '@/domain/Schema.ts';
import { ref, computed } from 'vue';

type SchemaSaveHandler = ( schema: Schema, comment: string ) => Promise<void>;

const props = defineProps<{
	initialSchema: Schema;
	open: boolean;
	onSave: SchemaSaveHandler;
}>();

const emit = defineEmits<{
	'update:open': [ value: boolean ];
	'saved': [ schema: Schema ];
}>();

const open = computed( {
	get: () => props.open,
	set: ( value: boolean ) => emit( 'update:open', value )
} );

const schemaEditor = ref<SchemaEditorExposes | null>( null );
const hasOverflow = ref( false );

function onOverflow( overflow: boolean ): void {
	hasOverflow.value = overflow;
}

const handleSave = async ( summary: string ): Promise<void> => {
	if ( !schemaEditor.value ) {
		return;
	}

	const schema = schemaEditor.value.getSchema();
	const schemaName = schema.getName();
	const editSummary = summary || 'Update schema via NeoWiki UI'; // TODO: i18n

	try {
		await props.onSave( schema, editSummary );
		// TODO: i18n
		mw.notify( editSummary, { title: `Updated ${ schemaName } schema`, type: 'success' } );
		emit( 'saved', schema );
		open.value = false;
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

.ext-neowiki-schema-editor-dialog {
	&.cdx-dialog {
		max-width: @size-5600;

		.cdx-dialog__body {
			padding: 0;
			display: grid;
			overflow: hidden;
		}
	}
}
</style>
