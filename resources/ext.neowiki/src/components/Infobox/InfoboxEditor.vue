<template>
	<CdxDialog
		v-model:open="isOpen"
		:title="props.selectedType ?
			$i18n( 'neowiki-infobox-editor-dialog-title-create', props.selectedType ).text() :
			$i18n( 'neowiki-infobox-editor-dialog-title-create-blank' ).text()"
		class="infobox-editor">
		<CdxField>
			<CdxTextInput v-model="name" />

			<template #label>
				{{ $i18n( 'neowiki-infobox-editor-subject-label' ).text() }}
			</template>
		</CdxField>
		<CdxButton @click="submit">
			{{ $i18n( 'neowiki-infobox-editor-create-button' ).text() }}
		</CdxButton>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxDialog, CdxTextInput, CdxButton, CdxField } from '@wikimedia/codex';

const props = defineProps<{
	selectedType: string;
}>();

const emit = defineEmits( [ 'complete' ] );
const isOpen = ref( false );
const name = ref( '' );

const openDialog = (): void => {
	isOpen.value = true;
	name.value = '';
};

const submit = (): void => {
	console.log( `Creating ${ props.selectedType || 'subject' }: ${ name.value }` );
	isOpen.value = false;
	emit( 'complete' );
};

defineExpose( { openDialog } );
</script>

<style>
.cdx-dialog.infobox-editor {
	max-width: 800px;
}
</style>
