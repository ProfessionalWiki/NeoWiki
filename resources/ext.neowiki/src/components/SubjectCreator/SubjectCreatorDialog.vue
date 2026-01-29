<template>
	<div class="ext-neowiki-subject-creator-container">
		<CdxButton
			class="ext-neowiki-subject-creator-trigger"
			@click="open = true"
		>
			{{ $i18n( 'neowiki-subject-creator-button-label' ).text() }}
		</CdxButton>
		<CdxDialog
			v-model:open="open"
			class="ext-neowiki-subject-creator-dialog"
			:title="$i18n( 'neowiki-subject-creator-title' ).text()"
			:use-close-button="true"
			@default="open = false"
		>
			<SubjectCreator @create="onSubjectCreated" />
		</CdxDialog>

		<SubjectEditorDialog
			v-if="createdSubject"
			v-model:open="isSubjectEditorOpen"
			:subject="createdSubject"
			:on-save="handleCreateSubject"
			:on-save-schema="handleSaveSchema"
		/>
	</div>
</template>

<script setup lang="ts">
import { ref, shallowRef } from 'vue';
import { CdxButton, CdxDialog } from '@wikimedia/codex';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Subject } from '@/domain/Subject.ts';
import { Schema } from '@/domain/Schema.ts';
import SubjectCreator from '@/components/SubjectCreator/SubjectCreator.vue';
import SubjectEditorDialog from '@/components/SubjectEditor/SubjectEditorDialog.vue';

const open = ref( false );
const isSubjectEditorOpen = ref( false );
const createdSubject = shallowRef<Subject | null>( null );

const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();

function onSubjectCreated( subject: Subject ): void {
	createdSubject.value = subject;
	isSubjectEditorOpen.value = true;
	open.value = false;
}

async function handleCreateSubject( subject: Subject, _summary: string ): Promise<void> {
	await subjectStore.createMainSubject(
		mw.config.get( 'wgArticleId' ),
		subject.getLabel(),
		subject.getSchemaName(),
		subject.getStatements()
	);
	// Reload to show the new subject
	window.location.reload();
}

async function handleSaveSchema( schema: Schema, comment?: string ): Promise<void> {
	await schemaStore.saveSchema( schema, comment );
}

</script>
