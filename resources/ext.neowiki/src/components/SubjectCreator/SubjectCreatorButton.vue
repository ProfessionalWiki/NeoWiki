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
			v-if="mayCreate"
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
import type { InitialPage } from '@/components/SubjectCreator/InitialPage.ts';
import type { SubjectCreatorButtonProps } from '@/components/SubjectCreator/subjectCreatorButtonProps.ts';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';

const props = defineProps<SubjectCreatorButtonProps>();

const creatorOpen = ref( false );
const denialOpen = ref( false );
const permissionKnown = ref( false );

// Storing on a page that exists needs the right to edit it; a new page also needs the right to create
// it. That decides which dialog the button opens; the button itself shows either way.
const {
	canCreateMainSubject,
	canCreateSubjectPage,
	checkPermissions,
	checkCreateSubjectPagePermission
} = useSubjectPermissions();

const existingPageId = computed( (): number | null => existingPageIdOf( props.initialPage ) );

const mayCreate = computed( (): boolean =>
	existingPageId.value === null ? canCreateSubjectPage.value : canCreateMainSubject.value
);

/**
 * The page a fixed target stores the Subject on, when that page exists. Null where the Subject may go on a new page.
 */
function existingPageIdOf( initialPage: InitialPage ): number | null {
	if ( !initialPage.fixed ) {
		return null;
	}

	if ( initialPage.choice === 'thisPage' ) {
		return Number( mw.config.get( 'wgArticleId' ) );
	}

	return initialPage.page?.pageId ?? null;
}

onMounted( async (): Promise<void> => {
	if ( existingPageId.value === null ) {
		await checkCreateSubjectPagePermission();
	} else {
		await checkPermissions( existingPageId.value );
	}

	permissionKnown.value = true;
} );

const closeAction: DialogAction = { label: mw.msg( 'cdx-dialog-close-button-label' ) };

const denialReason = computed( (): string => {
	const reason = mw.config.get( 'wgNeoWikiCreateSubjectPageDeniedReason' );

	return typeof reason === 'string' ? reason : mw.msg( 'neowiki-create-subject-denied' );
} );

function openDialog(): void {
	if ( mayCreate.value ) {
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
