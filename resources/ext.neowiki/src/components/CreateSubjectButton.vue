<template>
	<div>
		<CdxButton @click="openDialog">
			<CdxIcon :icon="cdxIconAdd" />
			{{ $i18n( 'neowiki-create-button' ).text() }}
		</CdxButton>
		<CreateSubjectDialog
			ref="createSubjectDialog"
			:can-create-schemas="canCreateSchemas"
			@next="onSubjectTypeSelected"
		/>
		<InfoboxEditor
			ref="infoboxEditorDialog"
			:selected-schema="selectedSchema"
			:is-edit-mode="false"
			:can-edit-schema="canEditSchema"
			@complete="onCreationComplete"
			@back="onInfoboxBack"
		/>
	</div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import CreateSubjectDialog from '@/components/CreateSubjectDialog.vue';
import InfoboxEditor from '@/components/Editor/InfoboxEditor.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const createSubjectDialog = ref<typeof CreateSubjectDialog|null>( null );
const infoboxEditorDialog = ref<typeof InfoboxEditor|null>( null );
const selectedSchema = ref( '' );

const schemaAuthorizer = NeoWikiServices.getSchemaAuthorizer();
const canCreateSchemas = ref( false );
const canEditSchema = ref( false );

onMounted( async (): Promise<void> => {
	canCreateSchemas.value = await schemaAuthorizer.canCreateSchemas();
} );

const openDialog = (): void => {
	if ( createSubjectDialog.value === null ) {
		return;
	}

	createSubjectDialog.value.openDialog();
};

const onSubjectTypeSelected = async ( type: string ): Promise<void> => {
	if ( infoboxEditorDialog.value === null ) {
		return;
	}

	selectedSchema.value = type;
	canEditSchema.value = await schemaAuthorizer.canEditSchema( type );
	infoboxEditorDialog.value.openDialog();
};

const onCreationComplete = (): void => {
	console.log( 'Creation process completed' );
	selectedSchema.value = '';
	canEditSchema.value = false;
};

const onInfoboxBack = (): void => {
	if ( createSubjectDialog.value === null ) {
		return;
	}

	createSubjectDialog.value.openDialog();
};
</script>
