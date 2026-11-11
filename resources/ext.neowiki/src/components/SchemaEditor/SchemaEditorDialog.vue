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
				>
					<template #supporting-text>
						<EditableText
							ref="descriptionField"
							:model-value="description"
							:edit-button-label="$i18n( 'neowiki-schema-editor-description-edit' ).text()"
							:input-aria-label="$i18n( 'neowiki-schema-editor-description' ).text()"
							:expand-label="$i18n( 'neowiki-schema-editor-description-expand' ).text()"
							:collapse-label="$i18n( 'neowiki-schema-editor-description-collapse' ).text()"
							:add-label="$i18n( 'neowiki-schema-editor-description-add' ).text()"
							:multiline="true"
							:clamp-lines="2"
							@dirty="descriptionDirty = $event"
							@update:model-value="onDescriptionChanged"
						/>

						<!-- Shown, not offered: it is set when the Schema is created. -->
						<p
							v-if="props.initialSchema.subjectsAreDependent()"
							class="ext-neowiki-schema-editor-dialog__dependence"
						>
							{{ $i18n( 'neowiki-schema-subjects-are-dependent' ).text() }}
						</p>
					</template>
				</DialogHeader>
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
import EditableText from '@/components/common/EditableText.vue';
import SchemaCreatorDialog from '@/components/SchemaCreator/SchemaCreatorDialog.vue';
import { CdxDialog } from '@wikimedia/codex';
import DialogHeader from '@/components/common/DialogHeader.vue';
import { Schema } from '@/domain/Schema.ts';
import { computed, nextTick, ref, watch } from 'vue';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useSchemaCreation } from '@/composables/useSchemaCreation.ts';
import { useSaveGuard } from '@/composables/useSaveGuard.ts';

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
const { hasChanged: committed, markChanged, resetChanged } = useChangeDetection();

// A description still being typed is already something to save, and nothing once it is discarded.
const descriptionDirty = ref( false );

const descriptionField = ref<InstanceType<typeof EditableText> | null>( null );

const hasChanged = computed( (): boolean => committed.value || descriptionDirty.value );

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

const { saving, save: handleSave } = useSaveGuard( writeSchema );

async function writeSchema( summary: string ): Promise<void> {
	// A save that moves no focus has not committed a description still being typed. The editor
	// reads the description as a prop, which reaches it with the next render.
	descriptionField.value?.commit();
	await nextTick();

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

		/* Secondary to the title. Nested under the header to out-rank
			Codex's runtime-injected two-class subtitle rule. */
		.cdx-dialog__header .cdx-dialog__header__subtitle {
			font-size: @font-size-small;
		}
	}

	&__dependence {
		margin: @spacing-25 0 0;
		color: @color-subtle;
		font-size: @font-size-small;
	}
}
</style>
