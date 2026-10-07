<template>
	<CdxTable
		class="ext-neowiki-recent-subjects"
		:columns="columns"
		:data="rows"
		:caption="$i18n( 'neowiki-subjects-recent' ).text()"
		:pending="loading"
	>
		<template #item-name="{ row }">
			<SubjectSummaryCell
				column="name"
				:summary="row"
			/>
		</template>

		<template #item-id="{ row }">
			<SubjectSummaryCell
				column="id"
				:summary="row"
			/>
		</template>

		<template #item-schema="{ row }">
			<SubjectSummaryCell
				column="schema"
				:summary="row"
			/>
		</template>

		<template #item-page="{ row }">
			<SubjectSummaryCell
				column="page"
				:summary="row"
			/>
		</template>

		<template #item-edited="{ row }">
			<SubjectSummaryCell
				column="edited"
				:summary="row"
			/>
		</template>

		<template #footer>
			<a :href="listUrl">{{ $i18n( 'neowiki-subjects-view-all' ).text() }}</a>
		</template>

		<!-- Codex shows the empty state whenever the table has no rows, so also while they load. -->
		<template
			v-if="!loading"
			#empty-state
		>
			{{ emptyText }}
		</template>
	</CdxTable>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { CdxTable } from '@wikimedia/codex';
import SubjectSummaryCell from './SubjectSummaryCell.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { isSubjectFirst } from '@/wikiMode.ts';
import type { SubjectSummary } from '@/application/SubjectSummaryLookup.ts';
import { subjectColumns } from '@/presentation/subjectSummaryTable.ts';

const RECENT_COUNT = 5;

const lookup = NeoWikiServices.getSubjectSummaryLookup();

const rows = ref<SubjectSummary[]>( [] );
const loading = ref( true );
const loadFailed = ref( false );

const columns = subjectColumns( { showSchema: true, showPage: !isSubjectFirst(), sortable: false } );
const listUrl = mw.util.getUrl( 'Special:Subjects' );
const emptyText = computed( () => mw.msg( loadFailed.value ? 'neowiki-subjects-load-error' : 'neowiki-subjects-empty' ) );

onMounted( async () => {
	try {
		rows.value = ( await lookup.getSubjectSummaries( {
			schema: null, search: '', sort: 'newest', direction: 'desc', cursor: null, limit: RECENT_COUNT
		} ) ).subjects;
	} catch {
		loadFailed.value = true;
	}

	loading.value = false;
} );
</script>
