<template>
	<div>
		<!-- Codex adds the dividers only for a dialog whose own body scrolls, which leaves the
			rule between the columns running into nothing whenever it does not, so the variant is
			asked for by name. Same reasoning as the subject editor. -->
		<CdxDialog
			:open="props.open"
			:use-close-button="true"
			class="ext-neowiki-ui ext-neowiki-schema-creator-dialog cdx-dialog--dividers"
			:title="$i18n( 'neowiki-schema-creator-title' ).text()"
			@update:open="onDialogUpdateOpen"
		>
			<SchemaCreator
				ref="schemaCreatorRef"
				@change="markChanged"
			/>

			<template #footer>
				<SummaryAction
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
import SummaryAction from '@/components/common/SummaryAction.vue';
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

	const unparseable = schemaCreatorRef.value.unparseableInput();

	// Saving now would silently drop the text the user can still see.
	if ( unparseable !== null ) {
		mw.notify( unparseable.message, { title: unparseable.propertyName, type: 'error' } );
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

		/* Codex's own `overflow-y: auto` is kept, so anything the columns do not scroll
			themselves stays reachable at the body. `display: grid` hands the bounded height
			down to the one child. Carries `min-height: 0` to match the subject editor, whose
			comment records why it is inert here. */
		.cdx-dialog__body {
			padding: 0;
			min-height: 0;
			display: grid;
		}
	}
}
</style>
