<template>
	<div class="ext-neowiki-subject-editor-container">
		<CdxButton
			weight="quiet"
			:aria-label="$i18n( 'neowiki-infobox-edit-link' ).text()"
			@click="open = true"
		>
			<CdxIcon :icon="cdxIconEdit" />
		</CdxButton>
		<CdxDialog
			v-model:open="open"
			class="ext-neowiki-dialog"
			:title="$i18n( 'neowiki-subject-editor-title' ).text()"
			:use-close-button="true"
			@default="open = false"
		>
			<SubjectEditor
				ref="subjectEditorRef"
				:initial-label="props.subject.getLabel()"
				:initial-statements="props.subject.getStatements()"
			/>

			<!-- TODO: We should make this into a component-->
			<template #footer>
				<DialogFooter @save="handleSave" />
			</template>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import { ref, nextTick } from 'vue';
import SubjectEditor from '@/components/SubjectEditor/SubjectEditor.vue';
import DialogFooter from '@/components/common/DialogFooter.vue';
import { CdxButton, CdxDialog, CdxIcon } from '@wikimedia/codex';
import { cdxIconEdit } from '@wikimedia/codex-icons';
import { StatementList } from '@neo/domain/StatementList.ts';
import { Subject } from '@neo/domain/Subject.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';

const props = defineProps<{
	subject: Subject;
}>();

const emit = defineEmits<( e: 'update:subject', subject: Subject ) => void>();

const subjectStore = useSubjectStore();

interface SubjectEditorInstance {
	getSubjectData: () => StatementList;
}

const open = ref( false );
const subjectEditorRef = ref<SubjectEditorInstance | null>( null );

const handleSave = async ( summary: string ): Promise<void> => {
	await nextTick();

	if ( !subjectEditorRef.value ) {
		return;
	}

	const updatedSubject = props.subject.withStatements( subjectEditorRef.value.getSubjectData() );
	const subjectName = updatedSubject.getLabel();
	try {
		await subjectStore.updateSubject( updatedSubject );
		mw.notify(
			summary || 'No edit summary provided.',
			{
				title: `Updated ${ subjectName }`,
				type: 'success'
			}
		);
		emit( 'update:subject', updatedSubject );
		open.value = false;
	} catch ( error ) {
		mw.notify(
			error instanceof Error ? error.message : String( error ),
			{
				title: `Failed to update ${ subjectName }.`,
				type: 'error'
			}
		);
	}
};

</script>
