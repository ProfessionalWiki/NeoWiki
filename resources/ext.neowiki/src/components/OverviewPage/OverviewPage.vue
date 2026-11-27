<template>
	<div class="ext-neowiki-overview">
		<div
			v-if="canCreateSubjectPage"
			class="ext-neowiki-overview__actions"
		>
			<CdxButton
				action="progressive"
				:weight="firstStepsReplaced ? 'normal' : 'primary'"
				@click="subjectStore.openSubjectCreator()"
			>
				<CdxIcon :icon="cdxIconAdd" />
				{{ $i18n( 'neowiki-createsubject-button' ).text() }}
			</CdxButton>
		</div>

		<component
			:is="notice.component"
			v-for="( notice, index ) in notices"
			:key="index"
		/>

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
					{{ link.label }}
					<CdxIcon :icon="cdxIconNext" size="small" />
				</template>
				<template #description>
					{{ link.description }}
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
import type { RegisteredNotice } from '@/presentation/FrontendRegistrar.ts';
import { CdxButton, CdxCard, CdxIcon, CdxMessage } from '@wikimedia/codex';
import {
	cdxIconAdd,
	cdxIconDatabase,
	cdxIconGlobe,
	cdxIconLayout,
	cdxIconListBullet,
	cdxIconNext,
	cdxIconPuzzle,
	cdxIconSettings,
	cdxIconTable
} from '@wikimedia/codex-icons';
import type { Icon } from '@wikimedia/codex-icons';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import RecentSubjectsTable from '@/components/SubjectsTable/RecentSubjectsTable.vue';
import { useSubjectPermissions } from '@/composables/useSubjectPermissions.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';

interface PageLink {
	page: string;
	label: string;
	description: string;
	icon: Icon;
}

const PAGE_LINKS: PageLink[] = [
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

const GRAPH_STORES_LINK: PageLink = {
	page: 'Special:GraphStores',
	label: 'neowiki-special-graphstores',
	description: 'neowiki-overview-graph-stores-description',
	icon: cdxIconDatabase
};

const CONFIGURATION_LINK: PageLink = {
	page: 'MediaWiki:NeoWiki',
	label: 'neowiki-overview-configuration',
	description: 'neowiki-overview-configuration-description',
	icon: cdxIconSettings
};

const props = defineProps<{
	canManageGraphStores: boolean;
	canEditConfiguration: boolean;
	wikiHasSubjects: boolean;
	/** Pages other extensions add, with their label and description in the reader's language. */
	extensionLinks: { page: string; label: string; description: string }[];
	/** What other extensions show above the cards. */
	notices: RegisteredNotice[];
}>();

const subjectStore = useSubjectStore();
const { canCreateSubjectPage, checkCreateSubjectPagePermission } = useSubjectPermissions();

const permissionKnown = ref( false );

// A notice in place of the first steps offers another way to start, which leaves creating a Subject second.
const firstStepsReplaced = computed( () => props.notices.some( ( notice ) => notice.replacesNote ) );

// The note points at the create button, so only those offered the button get it.
const showsFirstSteps = computed( () => !props.wikiHasSubjects && canCreateSubjectPage.value && !firstStepsReplaced.value );

// On a wiki without Subjects the table waits for the permission check, so it never loads for a user who gets the note,
// nor for one who gets a notice in its place.
const showsRecentSubjects = computed(
	() => props.wikiHasSubjects || ( permissionKnown.value && !showsFirstSteps.value && !firstStepsReplaced.value )
);

// The puzzle stands for an extension, whose pages are mapped after NeoWiki's own.
const pageLinks = computed( () => [
	...PAGE_LINKS.map( inReaderLanguage ),
	...props.extensionLinks.map( ( link ) => ( { ...link, icon: cdxIconPuzzle } ) ),
	...( props.canManageGraphStores ? [ inReaderLanguage( GRAPH_STORES_LINK ) ] : [] ),
	...( props.canEditConfiguration ? [ inReaderLanguage( CONFIGURATION_LINK ) ] : [] )
] );

function inReaderLanguage( link: PageLink ): PageLink {
	return { ...link, label: mw.msg( link.label ), description: mw.msg( link.description ) };
}

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
