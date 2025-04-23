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
				:initial-label="props.label"
				:initial-statements="props.statements"
				@update:is-modified="handleModifiedUpdate"
			/>

			<!-- TODO: We should make this into a component-->
			<template #footer>
				<DialogFooter :save-disabled="!isSubjectModified" @save="handleSave" />
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
import { Value } from '@neo/domain/Value.ts';

const props = defineProps<{
	label: string;
	statements: StatementList;
}>();

interface SubjectEditorInstance {
	getSubjectData: () => Record<string, Value | undefined>;
}

const open = ref( false );
const subjectEditorRef = ref<SubjectEditorInstance | null>( null );
const isSubjectModified = ref( false );

const handleModifiedUpdate = ( isModified: boolean ): void => {
	isSubjectModified.value = isModified;
};

const handleSave = async ( summary: string ): Promise<void> => {
	let modifiedStatements: Record<string, Value | undefined> | null = null;

	console.debug( 'Passed to SubjectEditor:', { label: props.label, statements: props.statements } );

	await nextTick();

	if ( subjectEditorRef.value ) {
		modifiedStatements = subjectEditorRef.value.getSubjectData();

		console.log( 'Received from SubjectEditor:', modifiedStatements );
	} else {
		console.warn( 'SubjectEditor ref not available on save.' );
	}

	mw.notify(
		summary || 'No edit summary provided',
		{
			title: '[SubjectEditorDialog] onSave',
			type: 'success'
		}
	);
	open.value = false;
};

</script>
