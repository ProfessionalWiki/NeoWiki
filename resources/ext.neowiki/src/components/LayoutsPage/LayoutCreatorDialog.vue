<template>
	<div>
		<CdxDialog
			:open="props.open"
			:use-close-button="true"
			class="ext-neowiki-layout-creator-dialog"
			:title="$i18n( 'neowiki-layout-creator-title' ).text()"
			@update:open="onDialogUpdateOpen"
		>
			<LayoutCreator
				ref="layoutCreatorRef"
				@change="markChanged"
			/>

			<template #footer>
				<EditSummary
					help-text=""
					:save-button-label="$i18n( 'neowiki-layout-creator-save' ).text()"
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
import LayoutCreator from './LayoutCreator.vue';
import type { LayoutCreatorExposes } from './LayoutCreator.vue';
import EditSummary from '@/components/common/EditSummary.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { Layout } from '@/domain/Layout.ts';
import type { LayoutSaveHandler } from '@/components/LayoutEditor/LayoutEditorDialog.vue';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';

const props = defineProps<{
	open: boolean;
	onSave: LayoutSaveHandler;
}>();

const emit = defineEmits<{
	'update:open': [ value: boolean ];
	'created': [ layout: Layout ];
}>();

const { hasChanged, markChanged, resetChanged } = useChangeDetection();

const layoutCreatorRef = ref<LayoutCreatorExposes | null>( null );

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
		layoutCreatorRef.value?.reset();
	}
} );

async function handleSave( summary: string ): Promise<void> {
	if ( !layoutCreatorRef.value ) {
		return;
	}

	const valid = await layoutCreatorRef.value.validate();

	if ( !valid ) {
		return;
	}

	const layout = layoutCreatorRef.value.getLayout();

	if ( !layout ) {
		return;
	}

	const editSummary = summary || mw.msg( 'neowiki-layout-creator-summary-default' );

	try {
		await props.onSave( layout, editSummary );
		mw.notify( mw.msg( 'neowiki-layout-creator-success', layout.getName() ), { type: 'success' } );
		emit( 'created', layout );
		close();
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-layout-creator-error', layout.getName() ),
				type: 'error'
			}
		);
	}
}
</script>

<style lang="less">
.ext-neowiki-layout-creator-dialog {
	&.cdx-dialog {
		.cdx-dialog__body {
			padding: 0;
		}
	}
}
</style>
