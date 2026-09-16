<!-- Sits over the end of the field holding the target, so the action reads as belonging to that
	value. It only ever opens: repointing the relation is typing in the field. -->
<template>
	<CdxButton
		class="ext-neowiki-relation-input__open-target"
		weight="quiet"
		type="button"
		:aria-label="label"
		:title="label"
		@click="emit( 'open', new SubjectId( props.target ) )"
	>
		<CdxIcon
			:icon="cdxIconArrowNext"
			size="small"
		/>
	</CdxButton>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconArrowNext } from '@wikimedia/codex-icons';
import { SubjectId } from '@/domain/SubjectId.ts';

const props = defineProps<{
	target: string;
	/** The target's own name. Empty while it is still being resolved. */
	name: string;
}>();

const emit = defineEmits<{
	open: [ SubjectId ];
}>();

// The control carries no text, so its name is the only thing saying what it opens; the id stands
// in rather than leaving a bare "Open".
const label = computed( (): string =>
	mw.message( 'neowiki-subject-editor-open-target', props.name || props.target ).text() );
</script>
