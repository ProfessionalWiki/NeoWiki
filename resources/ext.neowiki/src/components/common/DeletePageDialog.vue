<template>
	<CdxDialog
		:open="props.open"
		class="ext-neowiki-ui"
		:title="$i18n( 'neowiki-delete-confirm-title', props.displayName ).text()"
		:use-close-button="true"
		@update:open="onUpdateOpen"
	>
		<I18nSlot message-key="neowiki-delete-confirm-message">
			{{ props.typeLabel }} <strong>{{ props.displayName }}</strong>
		</I18nSlot>

		<template #footer>
			<SummaryAction
				help-text=""
				:label="$i18n( 'neowiki-delete-reason-label' ).text()"
				:placeholder="$i18n( 'neowiki-delete-reason-placeholder' ).text()"
				:save-button-label="$i18n( 'neowiki-delete-confirm-button' ).text()"
				save-button-action="destructive"
				:save-button-icon="cdxIconTrash"
				:save-disabled="false"
				@save="executeDelete"
			/>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { CdxDialog } from '@wikimedia/codex';
import { cdxIconTrash } from '@wikimedia/codex-icons';
import SummaryAction from '@/components/common/SummaryAction.vue';
import I18nSlot from '@/components/common/I18nSlot.vue';

const props = defineProps<{
	open: boolean;
	// The full title of the page to delete, e.g. `Schema:Person`.
	pageTitle: string;
	// The bare name shown in the confirmation message and notifications.
	displayName: string;
	// The lowercase entity noun shown before the name, e.g. `schema`, so the message reads
	// "The schema Person will be deleted" rather than exposing that it is a wiki page.
	typeLabel: string;
}>();

const emit = defineEmits<{
	'update:open': [ value: boolean ];
	'deleted': [];
}>();

function onUpdateOpen( value: boolean ): void {
	emit( 'update:open', value );
}

// The dialog closes as soon as the deletion is confirmed; success and failure are then surfaced
// through notifications, matching the behaviour of the per-page dialogs this replaces.
async function executeDelete( summary: string ): Promise<void> {
	emit( 'update:open', false );

	const reason = summary || mw.msg( 'neowiki-delete-summary-default' );

	try {
		const api = new mw.Api();
		const token = await api.getEditToken();
		await api.post( {
			action: 'delete',
			title: props.pageTitle,
			reason: reason,
			token: token
		} );
		mw.notify( mw.msg( 'neowiki-delete-success', props.displayName ), { type: 'success' } );
		emit( 'deleted' );
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: mw.msg( 'neowiki-delete-error', props.displayName ),
				type: 'error'
			}
		);
	}
}
</script>
