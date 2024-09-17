<template>
	<div>
		<CdxButton @click="openDialog">
			<CdxIcon :icon="cdxIconAdd" />
			{{ $i18n( 'neowiki-create-button' ).text() }}
		</CdxButton>
		<CreateSubjectDialog ref="createSubjectDialog" @next="onSubjectTypeSelected" />
		<InfoboxEditor
			ref="infoboxEditorDialog"
			:selected-type="selectedType"
			:is-edit-mode="false"
			@complete="onCreationComplete"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import CreateSubjectDialog from '@/components/CreateSubject/CreateSubjectDialog.vue';
import InfoboxEditor from '@/components/Infobox/InfoboxEditor.vue';

const createSubjectDialog = ref<InstanceType<typeof CreateSubjectDialog> | null>( null );
const infoboxEditorDialog = ref<InstanceType<typeof InfoboxEditor> | null>( null );
const selectedType = ref( '' );

const openDialog = (): void => {
	if ( createSubjectDialog.value ) {
		createSubjectDialog.value.openDialog();
	}
};

const onSubjectTypeSelected = ( type: string ): void => {
	if ( infoboxEditorDialog.value ) {
		selectedType.value = type;
		infoboxEditorDialog.value.openDialog();
	}
};

const onCreationComplete = ( statements: { property: string; value: string; type: string }[] ): void => {
	console.log( 'Creation process completed', statements );
	// Here you would typically handle the creation of the new subject
};
</script>
