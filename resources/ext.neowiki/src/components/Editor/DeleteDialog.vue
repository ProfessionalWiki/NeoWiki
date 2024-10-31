<template>
	<CdxDialog
		:open="isOpen"
		:title="$i18n( 'neowiki-delete-dialog', itemName ).text()"
		:primary-action="primaryAction"
		:default-action="defaultAction"
		@primary="onPrimaryAction"
		@default="$emit( 'close' )"
	>
		<p v-html="$i18n( 'neowiki-delete-dialog-confirmation-message', itemName ).text()" />
	</CdxDialog>
</template>

<script setup lang="ts">
import { CdxDialog } from '@wikimedia/codex';
import type { PrimaryDialogAction, DialogAction } from '@wikimedia/codex';

defineProps<{
	itemName: string;
	isOpen: boolean;
}>();

const emit = defineEmits( [ 'delete', 'close' ] );

const primaryAction: PrimaryDialogAction = {
	label: mw.message( 'neowiki-delete-dialog-button-primary-text' ).text(),
	actionType: 'destructive'
};

const defaultAction: DialogAction = {
	label: mw.message( 'neowiki-delete-dialog-button-cancel-text' ).text()
};

const onPrimaryAction = (): void => {
	emit( 'delete' );
};
</script>
