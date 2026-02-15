<template>
	<div>
		<CdxDialog
			:open="props.open"
			:use-close-button="true"
			class="ext-neowiki-schema-editor-dialog"
			:class="{ 'cdx-dialog--dividers': hasOverflow }"
			:title="$i18n( 'neowiki-editing-schema', props.initialSchema.getName() ).text()"
			@update:open="onDialogUpdateOpen"
		>
			<SchemaEditor
				ref="schemaEditor"
				:initial-schema="initialSchema"
				@overflow="onOverflow"
				@change="markChanged"
			/>

			<template #footer>
				<EditSummary
					:help-text="$i18n( 'neowiki-edit-summary-help-text-schema' ).text()"
					:save-button-label="$i18n( 'neowiki-save-schema' ).text()"
					@save="handleSave"
				/>
			</template>
		</CdxDialog>

		<CloseConfirmationDialog
			:open="confirmationOpen"
			@discard="confirmClose"
			@keep-editing="cancelClose"
		/>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor, { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { CdxDialog } from '@wikimedia/codex';
import { Schema } from '@/domain/Schema.ts';
import { ref, watch } from 'vue';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';

export type SchemaSaveHandler = ( schema: Schema, comment: string ) => Promise<void>;

const props = defineProps<{
	initialSchema: Schema;
	open: boolean;
	onSave: SchemaSaveHandler;
}>();

const emit = defineEmits<{
	'update:open': [ value: boolean ];
	'saved': [ schema: Schema ];
}>();

const schemaEditor = ref<SchemaEditorExposes | null>( null );
const hasOverflow = ref( false );
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

function close(): void {
	emit( 'update:open', false );
}

const { confirmationOpen, requestClose, confirmClose, cancelClose } = useCloseConfirmation( hasChanged, close );

function onDialogUpdateOpen( value: boolean ): void {
	if ( !value ) {
		requestClose();
	}
}

watch( () => props.open, ( isOpen ) => {
	if ( isOpen ) {
		resetChanged();
	}
} );

function onOverflow( overflow: boolean ): void {
	hasOverflow.value = overflow;
}

const handleSave = async ( summary: string ): Promise<void> => {
	if ( !schemaEditor.value ) {
		return;
	}

	const schema = schemaEditor.value.getSchema();
	const schemaName = schema.getName();
	const editSummary = summary || mw.msg( 'neowiki-schema-editor-summary-default' );

	try {
		await props.onSave( schema, editSummary );
		mw.notify( mw.msg( 'neowiki-schema-editor-success', schemaName ), { type: 'success' } );
		emit( 'saved', schema );
		close();
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-schema-editor-error', schemaName ),
				type: 'error'
			}
		);
	}
};

defineExpose( { hasChanged } );
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
