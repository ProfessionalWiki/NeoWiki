<template>
	<a
		v-if="column === 'name'"
		:href="nameUrl"
	>{{ name }}</a>
	<SchemaNameDisplay
		v-else-if="column === 'schema'"
		:schema-name="summary.schema"
		:display-name="schemaDisplayNameOf( summary.schema, summary.schemaLabels )"
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
import { schemaDisplayNameOf } from '@/presentation/schemaDisplayName.ts';
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
