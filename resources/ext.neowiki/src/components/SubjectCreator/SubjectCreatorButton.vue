<template>
	<div v-if="permissionKnown">
		<CdxButton
			action="progressive"
			weight="primary"
			@click="openDialog"
		>
			<CdxIcon :icon="cdxIconAdd" />
			{{ buttonLabel }}
		</CdxButton>

		<SubjectCreatorDialog
			v-if="canCreateSubjectPage"
			v-model:open="creatorOpen"
			:host-page="hostPage"
			:initial-schema-name="schemaName"
			:initial-page="initialPage"
		/>

		<CdxDialog
			v-else
			v-model:open="denialOpen"
			class="ext-neowiki-ui"
			:title="$i18n( 'permissionserrors' ).text()"
			:default-action="closeAction"
			@default="denialOpen = false"
		>
			{{ denialReason }}
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { CdxButton, CdxDialog, CdxIcon } from '@wikimedia/codex';
import type { DialogAction } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import type { SubjectCreatorButtonProps } from '@/components/SubjectCreator/subjectCreatorButtonProps.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';

const props = defineProps<SubjectCreatorButtonProps>();

const creatorOpen = ref( false );
const denialOpen = ref( false );
const permissionKnown = ref( false );

// The right to create a page for a Subject is the strictest a save may need, so it decides which
// dialog the button opens; the button itself shows either way.
const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();

onMounted( async (): Promise<void> => {
	await checkCreateSubjectPagePermission();
	permissionKnown.value = true;
} );

const closeAction: DialogAction = { label: mw.msg( 'cdx-dialog-close-button-label' ) };

const denialReason = computed( (): string => {
	const reason = mw.config.get( 'wgNeoWikiCreateSubjectPageDeniedReason' );

	return typeof reason === 'string' ? reason : mw.msg( 'neowiki-create-subject-denied' );
} );

function openDialog(): void {
	if ( canCreateSubjectPage.value ) {
		creatorOpen.value = true;
	} else {
		denialOpen.value = true;
	}
}

const buttonLabel = computed( (): string => {
	if ( props.text !== undefined ) {
		return props.text;
	}

	return props.schemaName === undefined ?
		mw.msg( 'neowiki-create-subject-default-label' ) :
		mw.msg( 'neowiki-schema-create-subject', props.schemaName );
} );
</script>
