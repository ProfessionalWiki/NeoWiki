<template>
	<CdxDialog
		:open="open"
		:title="$i18n( 'neowiki-close-confirmation-title' ).text()"
		:use-close-button="true"
		:stacked-actions="true"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@update:open="onUpdateOpen"
		@primary="$emit( 'discard' )"
		@default="$emit( 'keep-editing' )"
	>
		{{ $i18n( 'neowiki-close-confirmation-message' ).text() }}
	</CdxDialog>
</template>

<script setup lang="ts">
import { CdxDialog } from '@wikimedia/codex';
import type { PrimaryDialogAction, DialogAction } from '@wikimedia/codex';

defineProps<{
	open: boolean;
}>();

const emit = defineEmits<{
	'discard': [];
	'keep-editing': [];
}>();

const primaryAction: PrimaryDialogAction = {
	label: mw.msg( 'neowiki-close-confirmation-discard' ),
	actionType: 'destructive'
};

const defaultAction: DialogAction = {
	label: mw.msg( 'neowiki-close-confirmation-keep-editing' )
};

function onUpdateOpen( value: boolean ): void {
	if ( !value ) {
		emit( 'keep-editing' );
	}
}
</script>
