<template>
	<div>
		<CdxDialog
			:open="props.open"
			class="ext-neowiki-ui ext-neowiki-schema-editor-dialog"
			:title="dialogTitle"
			@update:open="onDialogUpdateOpen"
		>
			<template #header>
				<DialogHeader
					:title="dialogTitle"
					:use-close-button="true"
					@close="requestClose"
				/>
			</template>

			<SchemaEditor
				ref="schemaEditor"
				:initial-schema="initialSchema"
				:subjects-may-use-schema="subjectsMayUseSchema"
				@change="markChanged"
			>
				<template #schema>
					<CdxField :optional="true">
						<CdxTextArea
							v-model="description"
							:placeholder="$i18n( 'neowiki-schema-editor-description-placeholder' ).text()"
							@update:model-value="markChanged"
						/>
						<template #label>
							{{ $i18n( 'neowiki-schema-editor-description' ).text() }}
						</template>
					</CdxField>

					<!-- Shown, not offered: it is set when the Schema is created. -->
					<p
						v-if="props.initialSchema.subjectsAreDependent()"
						class="ext-neowiki-schema-editor-dialog__dependence"
					>
						{{ $i18n( 'neowiki-schema-subjects-are-dependent' ).text() }}
					</p>
				</template>
			</SchemaEditor>

			<template #footer>
				<SummaryAction
					:help-text="$i18n( 'neowiki-edit-summary-help-text-schema' ).text()"
					:save-button-label="$i18n( 'neowiki-save-schema' ).text()"
					:save-disabled="!hasChanged || saving"
					@save="handleSave"
				/>
			</template>
		</CdxDialog>

		<CloseConfirmationDialog
			:open="confirmationOpen"
			@discard="confirmClose"
			@keep-editing="cancelClose"
		/>

		<!-- Mounted only while open: Codex stacks dialogs in mount order. -->
		<SchemaCreatorDialog
			v-if="stackedSchemaName !== null"
			:open="true"
			:initial-name="stackedSchemaName"
			:subjects-are-dependent="stackedSubjectsAreDependent"
			@update:open="closeStackedSchemaCreator"
			@created="stackedSchemaCreated"
		/>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor, { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import SchemaCreatorDialog from '@/components/SchemaCreator/SchemaCreatorDialog.vue';
import { CdxDialog, CdxField, CdxTextArea } from '@wikimedia/codex';
import DialogHeader from '@/components/common/DialogHeader.vue';
import { Schema } from '@/domain/Schema.ts';
import { computed, ref, watch } from 'vue';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useSchemaCreation } from '@/composables/useSchemaCreation.ts';
import { useSaveGuard } from '@/composables/useSaveGuard.ts';
import { useSubjectsMayUseSchema } from '@/composables/useSubjectsMayUseSchema.ts';

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

function close(): void {
	emit( 'update:open', false );
}

const { confirmationOpen, requestClose, confirmClose, cancelClose } = useCloseConfirmation( hasChanged, close );

const {
	initialName: stackedSchemaName,
	subjectsAreDependent: stackedSubjectsAreDependent,
	created: stackedSchemaCreated,
	closed: closeStackedSchemaCreator
} = useSchemaCreation();

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

const subjectsMayUseSchema = useSubjectsMayUseSchema( () => props.initialSchema.getName(), () => props.open );

const { saving, save: handleSave } = useSaveGuard( writeSchema );

async function writeSchema( summary: string ): Promise<void> {
	if ( !schemaEditor.value ) {
		return;
	}

	// Saving now would silently drop the text the user can still see, or write a
	// property definition the wiki refuses.
	const blocker = schemaEditor.value.saveBlocker();

	if ( blocker !== null ) {
		schemaEditor.value.revealErrors();
		mw.notify( blocker.message, { title: blocker.propertyName, type: 'error' } );
		return;
	}

	const schema = schemaEditor.value.getSchema().withDescription( description.value );
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
}

defineExpose( { hasChanged } );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-schema-editor-dialog {
	&.cdx-dialog {
		.ext-neowiki-schema-dialog-size();
		.ext-neowiki-dialog-grid-body();
	}

	&__dependence {
		margin: @spacing-100 0 0;
		color: @color-subtle;
	}
}
</style>
