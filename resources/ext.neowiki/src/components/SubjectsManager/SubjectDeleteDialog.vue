<template>
	<CdxDialog
		:open="open"
		class="ext-neowiki-ui"
		:title="$i18n( 'neowiki-managesubjects-delete-confirm-title' ).text()"
		:use-close-button="true"
		@update:open="emit( 'update:open', $event )"
	>
		<I18nSlot message-key="neowiki-managesubjects-delete-confirm-message">
			<strong>{{ subjectName }}</strong>
		</I18nSlot>
		<template #footer>
			<SummaryAction
				help-text=""
				:save-button-label="$i18n( 'neowiki-managesubjects-delete-confirm-button' ).text()"
				:save-disabled="false"
				save-button-action="destructive"
				:save-button-icon="cdxIconTrash"
				@save="emit( 'confirm', $event )"
			/>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { CdxDialog } from '@wikimedia/codex';
import { cdxIconTrash } from '@wikimedia/codex-icons';
import I18nSlot from '@/components/common/I18nSlot.vue';
import SummaryAction from '@/components/common/SummaryAction.vue';

defineProps<{
	open: boolean;
	/** The name the confirmation asks about, so the user sees which Subject they are losing. */
	subjectName: string;
}>();

const emit = defineEmits<{
	'update:open': [ open: boolean ];
	/** Confirmed, carrying the edit summary the user typed. */
	confirm: [ summary: string ];
}>();
</script>
