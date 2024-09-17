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
		class="infobox-editor"
	>
		<div class="infobox-field">
			<CdxField>
				<template #label>
					{{ $i18n( 'neowiki-infobox-editor-subject-label' ).text() }}
				</template>
				<CdxTextInput v-model="name" />
			</CdxField>
		</div>

		<div
			v-for="( statement, index ) in statements"
			:key="index"
			class="statement-editor">
			<div class="statement-header">
				<div v-if="editingLabelIndex === index" class="statement-label-edit">
					<CdxTextInput
						v-model="statement.property"
						autofocus
						@blur="finishEditingLabel"
						@keyup.enter="finishEditingLabel"
					/>
				</div>
				<div
					v-else
					class="statement-label"
					@click="startEditingLabel( index )">
					{{ statement.property || $i18n( 'neowiki-infobox-editor-property-placeholder' ).text() }}
				</div>
				<CdxMenuButton
					v-model:selected="statement.type"
					:menu-items="typeOptions"
				>
					{{ statement.type || $i18n( 'neowiki-infobox-editor-select-type' ).text() }}
				</CdxMenuButton>
				<CdxButton
					weight="quiet"
					@click="removeStatement( index )"
				>
					<CdxIcon :icon="cdxIconTrash" />
				</CdxButton>
			</div>
			<CdxField>
				<CdxTextInput v-model="statement.value" />
			</CdxField>
		</div>

		<div class="add-statement">
			<CdxButton @click="addStatement">
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'neowiki-infobox-editor-add-statement' ).text() }}
			</CdxButton>
		</div>

		<template #footer>
			<CdxButton
				action="progressive"
				weight="primary"
				@click="submit"
			>
				{{ $i18n( 'neowiki-infobox-editor-save-button' ).text() }}
			</CdxButton>
		</template>
	</CdxDialog>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import {
	CdxDialog,
	CdxTextInput,
	CdxButton,
	CdxField,
	CdxIcon,
	CdxMenuButton
} from '@wikimedia/codex';
import { cdxIconTrash, cdxIconAdd } from '@wikimedia/codex-icons';

const props = defineProps<{
	selectedType: string;
	initialStatements?: { property: string; value: string; type: string }[];
	isEditMode: boolean;
}>();

const emit = defineEmits( [ 'complete' ] );

const isOpen = ref( false );
const name = ref( '' );
const statements = ref<{ property: string; value: string; type: string }[]>( [] );
const editingLabelIndex = ref( -1 );

const typeOptions = [
	{ value: 'text', label: 'Text' },
	{ value: 'number', label: 'Number' },
	{ value: 'date', label: 'Date' },
	{ value: 'link', label: 'Link' }
];

const openDialog = (): void => {
	isOpen.value = true;
	name.value = props.selectedType || '';
	statements.value = props.initialStatements ?
		[ ...props.initialStatements ] :
		[];
};

const addStatement = (): void => {
	statements.value.push( { property: '', value: '', type: '' } );
};

const removeStatement = ( index: number ): void => {
	statements.value.splice( index, 1 );
};

const startEditingLabel = ( index: number ): void => {
	editingLabelIndex.value = index;
};

const finishEditingLabel = (): void => {
	editingLabelIndex.value = -1;
};

const submit = (): void => {
	console.log( `${ props.isEditMode ? 'Updating' : 'Creating' } ${ props.selectedType || 'subject' }: ${ name.value }` );
	console.log( 'Statements:', statements.value );
	isOpen.value = false;
	emit( 'complete', statements.value );
};

defineExpose( { openDialog } );
</script>

<style scoped>
.cdx-dialog.infobox-editor {
	max-width: 800px;
}

.infobox-field,
.statement-editor,
.add-statement {
	margin-bottom: 16px;
}

.statement-header {
	display: flex;
	justify-content: space-between;
	align-items: center;
	margin-bottom: 8px;
}

.statement-label,
.statement-label-edit {
	flex-grow: 1;
	margin-right: 8px;
}

.statement-label {
	cursor: pointer;
	font-weight: bold;
}

.statement-label:hover {
	text-decoration: underline;
}
</style>
