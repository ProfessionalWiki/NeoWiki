<template>
	<div>
		<!-- Codex adds the dividers only for a dialog whose own body scrolls, which leaves the
			rule between the columns running into nothing whenever it does not, so the variant is
			asked for by name. Same reasoning as the subject editor. -->
		<CdxDialog
			:open="props.open"
			:use-close-button="true"
			class="ext-neowiki-ui ext-neowiki-schema-creator-dialog cdx-dialog--dividers"
			:title="title"
			@update:open="onDialogUpdateOpen"
		>
			<SchemaCreator
				ref="schemaCreatorRef"
				:initial-name="props.initialName"
				:duplicate-of="props.duplicateOf"
				@change="markChanged"
			/>

			<template #footer>
				<SummaryAction
					help-text=""
					:save-button-label="$i18n( 'neowiki-schema-creator-save' ).text()"
					:save-disabled="!hasChanged && !props.initialName"
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
			@update:open="closeStackedSchemaCreator"
			@created="stackedSchemaCreated"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxDialog } from '@wikimedia/codex';
import SchemaCreator from '@/components/SchemaCreator/SchemaCreator.vue';
import type { SchemaCreatorExposes } from '@/components/SchemaCreator/SchemaCreator.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { Schema } from '@/domain/Schema.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useSchemaCreation } from '@/composables/useSchemaCreation.ts';

const props = defineProps<{
	open: boolean;
	initialName?: string;
	/** The Schema to duplicate: the new one starts with all of it but the name. */
	duplicateOf?: Schema;
}>();

const emit = defineEmits<{
	'update:open': [ value: boolean ];
	'created': [ schema: Schema ];
}>();

const schemaStore = useSchemaStore();
const { hasChanged, markChanged, resetChanged } = useChangeDetection();

const schemaCreatorRef = ref<SchemaCreatorExposes | null>( null );

const title = computed( () => props.duplicateOf === undefined ?
	mw.msg( 'neowiki-schema-creator-title' ) :
	mw.msg( 'neowiki-schema-creator-duplicate-title', props.duplicateOf.getName() ) );

function close(): void {
	emit( 'update:open', false );
}

const { confirmationOpen, requestClose, confirmClose, cancelClose } = useCloseConfirmation( hasChanged, close );

const {
	initialName: stackedSchemaName,
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
		resetChanged();
	}
} );

async function handleSave( summary: string ): Promise<void> {
	if ( !schemaCreatorRef.value ) {
		return;
	}

	// Saving now would silently drop the text the user can still see, or write a
	// property definition the wiki refuses.
	const blocker = schemaCreatorRef.value.saveBlocker();

	if ( blocker !== null ) {
		schemaCreatorRef.value.revealErrors();
		mw.notify( blocker.message, { title: blocker.propertyName, type: 'error' } );
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

		/* The Schema editor dialog's height, for the same reason: a new Schema opens on its
			Label row, whose pane is much shorter than a property's. */
		@media ( min-width: @min-width-breakpoint-desktop ) {
			height: 60rem;
		}

		/* Codex's own `overflow-y: auto` is kept, so anything the columns do not scroll
			themselves stays reachable at the body, and it is what already lets this flex item
			shrink below its content. `display: grid` hands the bounded height down to the one
			child. `flex-grow`, because Codex 2 leaves the body at its content's height and pushes
			the footer down instead. */
		.cdx-dialog__body {
			padding: 0;
			display: grid;
			flex-grow: 1;
		}
	}
}
</style>
