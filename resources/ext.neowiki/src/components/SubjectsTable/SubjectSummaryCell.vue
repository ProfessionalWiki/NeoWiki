<template>
	<a
		v-if="column === 'name'"
		:href="nameUrl"
	>{{ name }}</a>
	<span
		v-else-if="column === 'id'"
		class="ext-neowiki-subject-summary-cell__id"
	>{{ summary.id }}</span>
	<SchemaNameDisplay
		v-else-if="column === 'schema'"
		:schema-name="summary.schema"
	/>
	<a
		v-else-if="column === 'page'"
		:href="pageUrl"
	>{{ summary.pageTitle }}</a>
	<time
		v-else-if="edited !== null"
		:datetime="summary.lastEdited"
		:title="edited.full"
	>{{ edited.relative }}</time>
	<span v-else>{{ summary.lastEdited }}</span>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import SchemaNameDisplay from '@/components/common/SchemaNameDisplay.vue';
import type { SubjectSummary } from '@/application/SubjectSummaryLookup.ts';
import {
	describeLastEdited, summaryName, summaryUrl, type SubjectColumnId
} from '@/presentation/subjectSummaryTable.ts';

const props = defineProps<{
	column: SubjectColumnId;
	summary: SubjectSummary;
}>();

const name = computed( () => summaryName( props.summary ) );
const nameUrl = computed( () => summaryUrl( props.summary ) );
const pageUrl = computed( () => mw.util.getUrl( props.summary.pageTitle ) );
const edited = computed( () => describeLastEdited( props.summary.lastEdited, new Date() ) );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-subject-summary-cell__id {
	font-family: @font-family-monospace;
}
</style>
