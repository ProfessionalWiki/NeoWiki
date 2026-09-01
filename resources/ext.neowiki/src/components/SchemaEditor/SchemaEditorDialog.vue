<template>
	<div>
		<!-- Codex adds the dividers only for a dialog whose own body scrolls, which leaves the
			rule between the columns running into nothing whenever it does not, so the variant is
			asked for by name. Same reasoning as the subject editor. -->
		<CdxDialog
			:open="props.open"
			class="ext-neowiki-ui ext-neowiki-schema-editor-dialog cdx-dialog--dividers"
			:title="dialogTitle"
			@update:open="onDialogUpdateOpen"
		>
			<template #header>
				<div class="cdx-dialog__header__title-group">
					<h2 class="cdx-dialog__header__title">
						{{ dialogTitle }}
					</h2>

					<div class="cdx-dialog__header__subtitle">
						<EditableText
							:model-value="description"
							:edit-button-label="$i18n( 'neowiki-schema-editor-description-edit' ).text()"
							:input-aria-label="$i18n( 'neowiki-schema-editor-description' ).text()"
							:expand-label="$i18n( 'neowiki-schema-editor-description-expand' ).text()"
							:collapse-label="$i18n( 'neowiki-schema-editor-description-collapse' ).text()"
							:add-label="$i18n( 'neowiki-schema-editor-description-add' ).text()"
							:multiline="true"
							:clamp-lines="2"
							@update:model-value="onDescriptionChanged"
						/>
					</div>
				</div>

				<CdxButton
					class="cdx-dialog__header__close-button"
					weight="quiet"
					type="button"
					:aria-label="$i18n( 'cdx-dialog-close-button-label' ).text()"
					@click="requestClose"
				>
					<CdxIcon :icon="cdxIconClose" />
				</CdxButton>
			</template>

			<SchemaEditor
				ref="schemaEditor"
				:initial-schema="initialSchema"
				:description="description"
				@change="markChanged"
			/>

			<template #footer>
				<SummaryAction
					:help-text="$i18n( 'neowiki-edit-summary-help-text-schema' ).text()"
					:save-button-label="$i18n( 'neowiki-save-schema' ).text()"
					:save-disabled="!hasChanged"
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
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import EditableText from '@/components/common/EditableText.vue';
import { CdxButton, CdxDialog, CdxIcon } from '@wikimedia/codex';
import { cdxIconClose } from '@wikimedia/codex-icons';
import { Schema } from '@/domain/Schema.ts';
import { computed, ref, watch } from 'vue';
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
const description = ref( props.initialSchema.getDescription() );
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

const dialogTitle = computed( () => mw.msg( 'neowiki-editing-schema', props.initialSchema.getName() ) );

watch( () => props.initialSchema, ( schema ) => {
	description.value = schema.getDescription();
} );

function onDescriptionChanged( value: string ): void {
	description.value = value;
	markChanged();
}

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
		// Reopening after a discard must not keep the abandoned description.
		description.value = props.initialSchema.getDescription();
		resetChanged();
	}
} );

const handleSave = async ( summary: string ): Promise<void> => {
	if ( !schemaEditor.value ) {
		return;
	}

	const unparseable = schemaEditor.value.unparseableInput();

	// Saving now would silently drop the text the user can still see.
	if ( unparseable !== null ) {
		mw.notify( unparseable.message, { title: unparseable.propertyName, type: 'error' } );
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

		/* Codex's own `overflow-y: auto` is kept, so anything the columns do not scroll
			themselves stays reachable at the body. `display: grid` hands the bounded height
			down to the one child. Carries `min-height: 0` to match the subject editor, whose
			comment records why it is inert here. */
		.cdx-dialog__body {
			padding: 0;
			min-height: 0;
			display: grid;
		}

		/* Replicate the Codex default dialog header styles, which a custom
			header slot does not get. Aligned to the start rather than the
			baseline, so the close button stays put as the description wraps. */
		.cdx-dialog__header {
			display: flex;
			align-items: flex-start;
			justify-content: flex-end;
			box-sizing: @box-sizing-base;
			width: @size-full;

			/* Secondary to the title, matching the subject editor's header.
				Nested under the header to out-rank Codex's runtime-injected
				two-class subtitle rule. */
			.cdx-dialog__header__subtitle {
				font-size: @font-size-small;
			}
		}

		.cdx-dialog__header__title-group {
			min-width: 0;
		}
	}
}
</style>
