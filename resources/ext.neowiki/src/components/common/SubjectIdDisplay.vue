<!-- A Subject's id, small and subdued, which a click copies. -->
<template>
	<button
		type="button"
		class="ext-neowiki-subject-id"
		:title="copyLabel"
		:aria-label="copyLabel"
		@click="copySubjectId"
	>
		<!-- Isolated left to right: an id from another Source may end in punctuation, which a
			right-to-left page would otherwise move to the front. -->
		<data :value="props.subjectId.text" dir="ltr">{{ props.subjectId.text }}</data>
	</button>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { SubjectId } from '@/domain/SubjectId.ts';
import { copyToClipboard } from '@/presentation/copyToClipboard.ts';

const props = defineProps<{
	subjectId: SubjectId;
}>();

const copyLabel = computed( (): string => mw.msg( 'neowiki-subject-id-copy', props.subjectId.text ) );

function copySubjectId(): Promise<void> {
	const id = props.subjectId.text;

	return copyToClipboard(
		id,
		mw.msg( 'neowiki-subject-id-copied', id ),
		mw.msg( 'neowiki-subject-id-copy-error' )
	);
}
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';
@import ( reference ) '@/assets/mixins.less';

.ext-neowiki-subject-id {
	.ext-neowiki-copy-button();
	// An id from another Source can be long: it ellipsizes rather than widening its line.
	max-width: @size-full;
	color: @color-subtle;
	font-size: @font-size-x-small;
}
</style>
