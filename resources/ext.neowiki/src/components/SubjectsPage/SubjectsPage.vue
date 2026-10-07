<template>
	<div class="ext-neowiki-subjects-page">
		<SubjectsTable
			:initial-schema="initialSchema"
			:can-create="canCreateSubjectPage"
			@update:schema="showSchemaInUrl"
			@create="openCreator"
		/>
		<SubjectCreatorDialog
			v-if="canCreateSubjectPage"
			v-model:open="subjectStore.subjectCreatorOpen"
			:host-page="null"
			:initial-schema-name="pinnedSchema"
		/>
	</div>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import SubjectsTable from '@/components/SubjectsTable/SubjectsTable.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';

defineProps<{
	initialSchema: string | null;
}>();

const subjectStore = useSubjectStore();
const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();
// The Schema the creator opens on, which the table's Schema menu decides.
const pinnedSchema = ref<string | undefined>( undefined );

onMounted( () => {
	checkCreateSubjectPagePermission();
} );

// The pin is set before the store is told, because the dialog reads it as it opens.
function openCreator( schema: string | undefined ): void {
	pinnedSchema.value = schema;
	subjectStore.openSubjectCreator();
}

// Replaced rather than pushed: the menu is a filter, not a step the Back button should undo.
function showSchemaInUrl( schema: string | null ): void {
	window.history.replaceState(
		window.history.state,
		'',
		mw.util.getUrl( schema === null ? 'Special:Subjects' : `Special:Subjects/${ schema }` )
	);
}
</script>
