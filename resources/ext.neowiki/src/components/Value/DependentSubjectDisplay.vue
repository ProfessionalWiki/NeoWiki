<template>
	<div class="ext-neowiki-dependent-subject-display">
		<div
			v-if="showsSchemaName"
			class="ext-neowiki-dependent-subject-display__schema"
		>
			<SchemaNameDisplay :schema-name="subject.getSchemaName()" />
		</div>
		<SubjectStatementsView :subject="subject" />
	</div>
</template>

<script setup lang="ts">
import type { Subject } from '@/domain/Subject';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import SubjectStatementsView from '@/components/SubjectsManager/SubjectStatementsView.vue';

defineProps<{
	subject: Subject;
	showsSchemaName: boolean;
}>();
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

/* On the base background, where a red link keeps its contrast, and set apart from the card or row around it by the
	border. */
.ext-neowiki-dependent-subject-display {
	padding: @spacing-25 @spacing-75;
	border: @border-width-base @border-style-base @border-color-subtle;
	border-inline-start: @border-width-thick @border-style-base @border-color-progressive;
	background-color: @background-color-base;

	& + & {
		margin-top: @spacing-50;
	}

	&__schema {
		padding-top: @spacing-50;
	}

	/* Its rows read as its own rather than as more rows of the Subject that holds it. */
	.ext-neowiki-subject-statements__item {
		padding-block: @spacing-25;
		border-top: 0;
	}
}
</style>
