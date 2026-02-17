<template>
	<div>
		<CdxDialog
			:open="props.open"
			:use-close-button="true"
			class="ext-neowiki-schema-creator-dialog"
			:class="{ 'cdx-dialog--dividers': hasOverflow }"
			:title="$i18n( 'neowiki-schema-creator-title' ).text()"
			@update:open="onDialogUpdateOpen"
		>
			<div class="ext-neowiki-schema-creator-dialog__name-section">
				<CdxField
					:status="nameStatus"
					:messages="nameError ? { error: nameError } : {}"
				>
					<CdxTextInput
						v-model="schemaName"
						:placeholder="$i18n( 'neowiki-schema-creator-name-placeholder' ).text()"
						@input="onNameInput"
					/>
					<template #label>
						{{ $i18n( 'neowiki-schema-creator-name-field' ).text() }}
					</template>
				</CdxField>
			</div>

			<SchemaEditor
				ref="schemaEditorRef"
				:initial-schema="emptySchema"
				@overflow="onOverflow"
				@change="markChanged"
			/>

			<template #footer>
				<EditSummary
					help-text=""
					:save-button-label="$i18n( 'neowiki-schema-creator-save' ).text()"
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
import { ref, watch } from 'vue';
import { CdxDialog, CdxField, CdxTextInput } from '@wikimedia/codex';
import type { ValidationStatusType } from '@wikimedia/codex';
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import type { SchemaEditorExposes } from '@/components/SchemaEditor/SchemaEditor.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { Schema } from '@/domain/Schema.ts';
import { PropertyDefinitionList } from '@/domain/PropertyDefinitionList.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';

const props = defineProps<{
	open: boolean;
}>();

const emit = defineEmits<{
	'update:open': [ value: boolean ];
	'created': [ schema: Schema ];
}>();

const schemaStore = useSchemaStore();
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

const emptySchema = new Schema( '', '', new PropertyDefinitionList( [] ) );

const schemaName = ref( '' );
const nameError = ref( '' );
const nameStatus = ref<ValidationStatusType>( 'default' );
const hasOverflow = ref( false );
const schemaEditorRef = ref<SchemaEditorExposes | null>( null );

function close(): void {
	emit( 'update:open', false );
}

const { confirmationOpen, requestClose, confirmClose, cancelClose } = useCloseConfirmation( hasChanged, close );

function onDialogUpdateOpen( value: boolean ): void {
	if ( !value ) {
		requestClose();
	}
}

function onOverflow( overflow: boolean ): void {
	hasOverflow.value = overflow;
}

function onNameInput(): void {
	nameError.value = '';
	nameStatus.value = 'default';
	markChanged();
}

watch( () => props.open, ( isOpen ) => {
	if ( isOpen ) {
		resetChanged();
		schemaName.value = '';
		nameError.value = '';
		nameStatus.value = 'default';
	}
} );

async function handleSave( summary: string ): Promise<void> {
	const name = schemaName.value.trim();

	if ( !name ) {
		nameError.value = mw.msg( 'neowiki-schema-creator-name-required' );
		nameStatus.value = 'error';
		return;
	}

	try {
		await schemaStore.getOrFetchSchema( name );
		nameError.value = mw.msg( 'neowiki-schema-creator-name-taken' );
		nameStatus.value = 'error';
		return;
	} catch {
		// Schema not found — name is available
	}

	const propertyDefinitions = schemaEditorRef.value ?
		schemaEditorRef.value.getSchema().getPropertyDefinitions() :
		new PropertyDefinitionList( [] );

	const description = schemaEditorRef.value ?
		schemaEditorRef.value.getSchema().getDescription() :
		'';

	const schema = new Schema( name, description, propertyDefinitions );
	const editSummary = summary || mw.msg( 'neowiki-schema-creator-summary-default' );

	try {
		await schemaStore.saveSchema( schema, editSummary );
		mw.notify( mw.msg( 'neowiki-schema-creator-success', name ), { type: 'success' } );
		emit( 'created', schema );
		close();
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-schema-creator-error', name ),
				type: 'error'
			}
		);
	}
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-schema-creator-dialog {
	&.cdx-dialog {
		max-width: @size-5600;

		.cdx-dialog__body {
			padding: 0;
			display: grid;
			overflow: hidden;
		}
	}

	&__name-section {
		padding: @spacing-100;
		border-block-end: @border-subtle;

		@media ( min-width: @min-width-breakpoint-desktop ) {
			padding: @spacing-150;
		}
	}
}
</style>
