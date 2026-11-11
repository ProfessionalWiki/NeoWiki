<template>
	<div>
		<CdxDialog
			:open="props.open"
			class="ext-neowiki-ui ext-neowiki-layout-editor-dialog"
			:title="$i18n( 'neowiki-editing-layout', props.initialLayout.getName() ).text()"
			@update:open="onDialogUpdateOpen"
		>
			<template #header>
				<DialogHeader
					:title="$i18n( 'neowiki-editing-layout', props.initialLayout.getName() ).text()"
					:use-close-button="true"
					@close="onDialogUpdateOpen( false )"
				/>
			</template>

			<EditorPanes>
				<LayoutEditor
					ref="layoutEditor"
					:initial-layout="initialLayout"
					@change="markChanged"
				/>
			</EditorPanes>

			<template #footer>
				<SummaryAction
					:help-text="$i18n( 'neowiki-edit-summary-help-text-layout' ).text()"
					:save-button-label="$i18n( 'neowiki-save-layout' ).text()"
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
	</div>
</template>

<script setup lang="ts">
import LayoutEditor from '@/components/LayoutEditor/LayoutEditor.vue';
import type { LayoutEditorExposes } from '@/components/LayoutEditor/LayoutEditor.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';
import CloseConfirmationDialog from '@/components/common/CloseConfirmationDialog.vue';
import { CdxDialog } from '@wikimedia/codex';
import DialogHeader from '@/components/common/DialogHeader.vue';
import EditorPanes from '@/components/common/EditorPanes.vue';
import { Layout } from '@/domain/Layout.ts';
import { ref, watch } from 'vue';
import { useChangeDetection } from '@/composables/useChangeDetection.ts';
import { useCloseConfirmation } from '@/composables/useCloseConfirmation.ts';
import { useSaveGuard } from '@/composables/useSaveGuard.ts';

export type LayoutSaveHandler = ( layout: Layout, comment: string ) => Promise<void>;

const props = defineProps<{
	initialLayout: Layout;
	open: boolean;
	onSave: LayoutSaveHandler;
}>();

const emit = defineEmits<{
	'update:open': [ value: boolean ];
	'saved': [ layout: Layout ];
}>();

const layoutEditor = ref<LayoutEditorExposes | null>( null );
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

const { saving, save: handleSave } = useSaveGuard( writeLayout );

async function writeLayout( summary: string ): Promise<void> {
	if ( !layoutEditor.value ) {
		return;
	}

	const layout = layoutEditor.value.getLayout();
	const layoutName = layout.getName();
	const editSummary = summary || mw.msg( 'neowiki-layout-editor-summary-default' );

	try {
		await props.onSave( layout, editSummary );
		mw.notify( mw.msg( 'neowiki-layout-editor-success', layoutName ), { type: 'success' } );
		emit( 'saved', layout );
		close();
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-layout-editor-error', layoutName ),
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

.ext-neowiki-layout-editor-dialog {
	&.cdx-dialog {
		.ext-neowiki-dialog-grid-body();
		max-width: @size-5600;
	}
}
</style>
