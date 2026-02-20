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
			<SchemaCreator
				ref="schemaCreatorRef"
				@overflow="onOverflow"
				@change="markChanged"
			/>

			<template #footer>
				<EditSummary
					help-text=""
					:save-button-label="$i18n( 'neowiki-schema-creator-save' ).text()"
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
import { ref, watch } from 'vue';
import { CdxDialog } from '@wikimedia/codex';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import type { SchemaCreatorExposes } from '@/components/SchemaCreator/SchemaCreator.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { Schema } from '@/domain/Schema.ts';
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

const hasOverflow = ref( false );
const schemaCreatorRef = ref<SchemaCreatorExposes | null>( null );

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

watch( () => props.open, ( isOpen ) => {
	if ( isOpen ) {
		resetChanged();
		schemaCreatorRef.value?.reset();
	}
} );

async function handleSave( summary: string ): Promise<void> {
	if ( !schemaCreatorRef.value ) {
		return;
	}

	const valid = await schemaCreatorRef.value.validate();

	if ( !valid ) {
		return;
	}

	const schema = schemaCreatorRef.value.getSchema();

	if ( !schema ) {
		return;
	}

	const editSummary = summary || mw.msg( 'neowiki-schema-creator-summary-default' );

	try {
		await schemaStore.saveSchema( schema, editSummary );
		mw.notify( mw.msg( 'neowiki-schema-creator-success', schema.getName() ), { type: 'success' } );
		emit( 'created', schema );
		close();
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-schema-creator-error', schema.getName() ),
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
}
</style>
