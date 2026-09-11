<template>
	<div class="ext-neowiki-create-subject-page">
		<CdxButton
			action="progressive"
			weight="primary"
			@click="subjectStore.openSubjectCreator()"
		>
			<CdxIcon :icon="cdxIconAdd" />
			{{ $i18n( 'neowiki-createsubject-button' ).text() }}
		</CdxButton>

		<SubjectCreatorDialog
			:page-has-main-subject="false"
			:choose-page="true"
			:initial-schema-name="props.schemaName"
		/>
	</div>
</template>

<script setup lang="ts">
import { onMounted } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { useSubjectStore } from '@/stores/SubjectStore.ts';

const props = defineProps<{
	schemaName?: string;
}>();

const subjectStore = useSubjectStore();

onMounted( () => {
	subjectStore.openSubjectCreator();
} );
</script>
