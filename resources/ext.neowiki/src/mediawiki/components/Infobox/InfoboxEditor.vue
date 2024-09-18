<template>
	<CdxDialog
		v-model:open="isOpen"
		:title="isEditMode ?
			( selectedType ?
				$i18n( 'neowiki-infobox-editor-dialog-title-edit', selectedType ).text() :
				$i18n( 'neowiki-infobox-editor-dialog-title-edit-blank' ).text() ) :
			( selectedType ?
				$i18n( 'neowiki-infobox-editor-dialog-title-create', selectedType ).text() :
				$i18n( 'neowiki-infobox-editor-dialog-title-create-blank' ).text() )"
		class="infobox-editor">
		<CdxField>
			<CdxTextInput v-model="name" />

			<template #label>
				{{ $i18n( 'neowiki-infobox-editor-subject-label' ).text() }}
			</template>
		</CdxField>
		<div
			v-for="( statement, index ) in statements"
			:key="index"
			class="statement-editor">
			<CdxField>
				<CdxTextInput v-model="statement.property" />
				<template #label>
					{{ $i18n( 'neowiki-infobox-editor-property-label' ).text() }}
				</template>
			</CdxField>
			<CdxField>
				<CdxTextInput v-model="statement.value" />
				<template #label>
					{{ $i18n( 'neowiki-infobox-editor-value-label' ).text() }}
				</template>
			</CdxField>
			<CdxButton action="destructive" @click="removeStatement( index )">
				<CdxIcon :icon="cdxIconTrash" />
				{{ $i18n( 'neowiki-infobox-editor-remove-statement' ).text() }}
			</CdxButton>
		</div>
		<CdxButton @click="addStatement">
			<CdxIcon :icon="cdxIconAdd" />
			{{ $i18n( 'neowiki-infobox-editor-add-statement' ).text() }}
		</CdxButton>
		<div>
			<CdxButton
				action="progressive"
				weight="primary"
				@click="submit">
				{{ $i18n( 'neowiki-infobox-editor-save-button' ).text() }}
			</CdxButton>
		</div>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxDialog, CdxTextInput, CdxButton, CdxField, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconTrash } from '@wikimedia/codex-icons';

const props = defineProps<{
	selectedType: string;
	initialStatements?: { property: string; value: string }[];
	isEditMode: boolean;
}>();

const emit = defineEmits( [ 'complete' ] );
const isOpen = ref( false );
const name = ref( '' );
const statements = ref<{ property: string; value: string }[]>( [] );

const openDialog = (): void => {
	isOpen.value = true;
	name.value = props.selectedType || '';
	statements.value = props.initialStatements ? [ ...props.initialStatements ] : [];
};

const addStatement = (): void => {
	statements.value.push( { property: '', value: '' } );
};

const removeStatement = ( index: number ): void => {
	statements.value.splice( index, 1 );
};

const submit = (): void => {
	console.log(
		`${ props.isEditMode ? 'Updating' : 'Creating' } ${ props.selectedType || 'subject' }: ${ name.value }`
	);
	console.log( 'Statements:', statements.value );
	isOpen.value = false;
	emit( 'complete', statements.value );
};

defineExpose( { openDialog } );
</script>

<style>
.cdx-dialog.infobox-editor {
	max-width: 800px;
}

.statement-editor {
	margin-bottom: 10px;
	padding-bottom: 10px;
	border-bottom: 1px solid #eaecf0;
}
</style>
