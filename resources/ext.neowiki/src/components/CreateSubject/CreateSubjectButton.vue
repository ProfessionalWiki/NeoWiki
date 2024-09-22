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
		<InfoboxEditor
			ref="infoboxEditorDialog"
			:selected-type="selectedType"
			:is-edit-mode="false"
			@complete="onCreationComplete"
			@back="onInfoboxBack"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import CreateSubjectDialog from '@/components/CreateSubject/CreateSubjectDialog.vue';
import InfoboxEditor from '@/components/Infobox/InfoboxEditor.vue';

const createSubjectDialog = ref<typeof CreateSubjectDialog|null>( null );
const infoboxEditorDialog = ref<typeof InfoboxEditor|null>( null );
const selectedType = ref( '' );

const openDialog = (): void => {
	if ( createSubjectDialog.value === null ) {
		return;
	}

	createSubjectDialog.value.openDialog();
};

const onSubjectTypeSelected = ( type: string ): void => {
	if ( infoboxEditorDialog.value === null ) {
		return;
	}

	selectedType.value = type;
	infoboxEditorDialog.value.openDialog();
};

const onCreationComplete = (): void => {
	console.log( 'Creation process completed' );
	selectedType.value = '';
};

const onInfoboxBack = (): void => {
	if ( createSubjectDialog.value === null ) {
		return;
	}

	createSubjectDialog.value.openDialog();
};
</script>
