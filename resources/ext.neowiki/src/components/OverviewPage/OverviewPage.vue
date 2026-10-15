<template>
	<div class="ext-neowiki-overview">
		<div
			v-if="canCreateSubjectPage"
			class="ext-neowiki-overview__actions"
		>
			<CdxButton
				action="progressive"
				weight="primary"
				@click="subjectStore.openSubjectCreator()"
			>
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'neowiki-createsubject-button' ).text() }}
			</CdxButton>
		</div>

		<CdxMessage
			v-if="showsFirstSteps"
			class="ext-neowiki-overview__first-steps"
		>
			{{ $i18n( 'neowiki-overview-first-steps' ).text() }}
		</CdxMessage>

		<div class="ext-neowiki-overview__pages">
			<CdxCard
				v-for="link in pageLinks"
				:key="link.page"
				:url="pageUrl( link.page )"
				:icon="link.icon"
			>
				<template #title>
					{{ $i18n( link.label ).text() }}
					<CdxIcon :icon="cdxIconNext" size="small" />
				</template>
				<template #description>
					{{ $i18n( link.description ).text() }}
				</template>
			</CdxCard>
		</div>

		<RecentSubjectsTable v-if="showsRecentSubjects" />

		<SubjectCreatorDialog
			v-if="canCreateSubjectPage"
			v-model:open="subjectStore.subjectCreatorOpen"
			:host-page="null"
		/>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref } from 'vue';
import { CdxButton, CdxCard, CdxIcon, CdxMessage } from '@wikimedia/codex';
import {
	cdxIconAdd,
	cdxIconDatabase,
	cdxIconGlobe,
	cdxIconLayout,
	cdxIconListBullet,
	cdxIconNext,
	cdxIconSettings,
	cdxIconTable
} from '@wikimedia/codex-icons';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import RecentSubjectsTable from '@/components/SubjectsTable/RecentSubjectsTable.vue';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';

const PAGE_LINKS = [
	{
		page: 'Special:Subjects',
		label: 'neowiki-special-subjects',
		description: 'neowiki-overview-subjects-description',
		icon: cdxIconListBullet
	},
	{
		page: 'Special:Schemas',
		label: 'neowiki-special-schemas',
		description: 'neowiki-overview-schemas-description',
		icon: cdxIconTable
	},
	{
		page: 'Special:Mappings',
		label: 'neowiki-special-mappings',
		description: 'neowiki-overview-mappings-description',
		icon: cdxIconGlobe
	},
	{
		page: 'Special:Layouts',
		label: 'neowiki-special-layouts',
		description: 'neowiki-overview-layouts-description',
		icon: cdxIconLayout
	}
];

const GRAPH_STORES_LINK = {
	page: 'Special:GraphStores',
	label: 'neowiki-special-graphstores',
	description: 'neowiki-overview-graph-stores-description',
	icon: cdxIconDatabase
};

const CONFIGURATION_LINK = {
	page: 'MediaWiki:NeoWiki',
	label: 'neowiki-overview-configuration',
	description: 'neowiki-overview-configuration-description',
	icon: cdxIconSettings
};

const props = defineProps<{
	canManageGraphStores: boolean;
	canEditConfiguration: boolean;
	wikiHasSubjects: boolean;
}>();

const subjectStore = useSubjectStore();
const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();

const permissionKnown = ref( false );

// The note points at the create button, so only those offered the button get it.
const showsFirstSteps = computed( () => !props.wikiHasSubjects && canCreateSubjectPage.value );

// On a wiki without Subjects the table waits for the permission check, so it never loads for a user who gets the note.
const showsRecentSubjects = computed(
	() => props.wikiHasSubjects || ( permissionKnown.value && !showsFirstSteps.value )
);

const pageLinks = computed( () => [
	...PAGE_LINKS,
	...( props.canManageGraphStores ? [ GRAPH_STORES_LINK ] : [] ),
	...( props.canEditConfiguration ? [ CONFIGURATION_LINK ] : [] )
] );

function pageUrl( page: string ): string {
	return mw.util.getUrl( page );
}

onMounted( async () => {
	await checkCreateSubjectPagePermission();
	permissionKnown.value = true;
} );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-overview {
	max-width: 64rem;

	// A block of its own: Codex sets the button's margin to zero after this stylesheet loads.
	&__actions {
		margin-bottom: @spacing-150;
	}

	&__first-steps {
		margin-bottom: @spacing-150;
	}

	&__pages {
		display: grid;
		grid-template-columns: repeat( 2, 1fr );
		gap: @spacing-100;
		margin-bottom: @spacing-150;

		@media ( max-width: @max-width-breakpoint-mobile ) {
			grid-template-columns: 1fr;
		}
	}
}
</style>
