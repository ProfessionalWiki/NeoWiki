<template>
	<div>
		<CdxButton @click="openDialog">
			<CdxIcon :icon="cdxIconAdd" />
			{{ $i18n( 'neowiki-create-button' ).text() }}
		</CdxButton>
		<CreateSubjectDialog
			ref="createSubjectDialog"
			@next="onSubjectTypeSelected"
		/>
		<SubjectEditor
			ref="subjectEditor"
			:selected-schema="selectedSchema"
			:is-edit-mode="false"
			:can-edit-schema="canEditSchema"
			@save="onCreationComplete"
			@back="onInfoboxBack"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import CreateSubjectDialog from '@/components/CreateSubjectDialog.vue';
import SubjectEditor from '@/components/Editor/SubjectEditor.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const createSubjectDialog = ref<typeof CreateSubjectDialog|null>( null );
const subjectEditor = ref<typeof SubjectEditor|null>( null );
const selectedSchema = ref( '' );

const schemaAuthorizer = NeoWikiServices.getSchemaAuthorizer();
const canEditSchema = ref( false );

const openDialog = (): void => {
	if ( createSubjectDialog.value === null ) {
		return;
	}

	createSubjectDialog.value.openDialog();
};

const onSubjectTypeSelected = async ( type: string ): Promise<void> => {
	if ( subjectEditor.value === null ) {
		return;
	}

	selectedSchema.value = type;
	canEditSchema.value = await schemaAuthorizer.canEditSchema( type );
	subjectEditor.value.openDialog();
};

const onCreationComplete = (): void => {
	selectedSchema.value = '';
	canEditSchema.value = false;
	// TODO: inject the new infobox instead
	window.location.reload();
};

const onInfoboxBack = (): void => {
	if ( createSubjectDialog.value === null ) {
		return;
	}

	createSubjectDialog.value.openDialog();
};
</script>
