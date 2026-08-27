<template>
	<CdxButton
		class="ext-neowiki-relation-input__edit-target"
		weight="quiet"
		type="button"
		:aria-label="$i18n( 'neowiki-subject-editor-edit-target' ).text()"
		@click="emit( 'edit', new SubjectId( props.target ) )"
	>
		<CdxIcon :icon="cdxIconEdit" />
	</CdxButton>
</template>

<script setup lang="ts">
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconEdit } from '@wikimedia/codex-icons';
import { SubjectId } from '@/domain/SubjectId.ts';

const props = defineProps<{
	target: string;
}>();

const emit = defineEmits<{
	edit: [ SubjectId ];
}>();
</script>
