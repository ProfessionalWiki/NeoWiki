<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<teleport
		v-for="view in viewsData"
		:key="`view-${view.subjectId.text}`"
		:to="view.element"
	>
		<component
			:is="resolveViewComponent( view )"
			:subject-id="view.subjectId"
			:can-edit-subject="view.canEditSubject"
			:layout-name="view.layoutName"
		/>
	</teleport>

	<teleport v-if="mainSubjectInFull !== null" :to="mainSubjectInFull.element">
		<SubjectPage
			:subject-id="mainSubjectInFull.subjectId"
			on-hosting-page
		/>
	</teleport>

	<teleport v-if="shouldShowSubjectCreator" to="#mw-content-text">
		<SubjectCreatorDialog
			v-model:open="subjectStore.subjectCreatorOpen"
			:host-page="hostPage"
			:relation-target="subjectStore.subjectCreatorTarget"
		/>
	</teleport>
</template>

<script setup lang="ts">
import type { Component } from 'vue';
import { onMounted, provide, ref } from 'vue';
import { SubjectId } from '@/domain/SubjectId';
import Infobox from '@/components/Views/Infobox.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import SubjectPage from '@/components/SubjectPage/SubjectPage.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { canEditSubjectOnItsPage } from '@/presentation/subjectEditPermission.ts';
import { RelationTargetCreationKey } from '@/components/Value/ValueDisplayContract.ts';

/** A View placeholder on the page, before its Subject is known. */
interface View {
	element: HTMLElement;
	subjectId: SubjectId;
	viewType?: string;
	layoutName?: string;
}

interface ViewData extends View {
	canEditSubject: boolean;
}

const props = defineProps<{
	showSubjectCreator: boolean;
	pageHasMainSubject: boolean;
}>();

const viewsData = ref<ViewData[]>( [] );

// Old revisions and diffs show the Main Subject as an infobox, also where the page places it in full.
const showsOldRevisionOrDiff = !isLatestRevision() || Boolean( mw.config.get( 'wgDiffNewId' ) );
const mainSubjectInFull = showsOldRevisionOrDiff ? null : findMainSubjectInFull();

const shouldShowSubjectCreator = ref( props.showSubjectCreator );
// The creator is teleported onto the page being viewed, which is the page it offers first.
const hostPage = ref( { hasMainSubject: props.pageHasMainSubject } );
const subjectStore = useSubjectStore();
const subjectPermissionHints = NeoWikiServices.getSubjectPermissionHints();

provide( RelationTargetCreationKey, shouldShowSubjectCreator );
const viewTypeRegistry = NeoWikiServices.getViewTypeRegistry();

function resolveViewComponent( viewData: ViewData ): Component {
	if ( viewData.layoutName ) {
		const layoutStore = useLayoutStore();
		const layout = layoutStore.getLayout( viewData.layoutName );
		if ( layout && viewTypeRegistry.hasType( layout.getType() ) ) {
			return viewTypeRegistry.getComponent( layout.getType() );
		}
	}

	if ( viewData.viewType !== undefined && viewTypeRegistry.hasType( viewData.viewType ) ) {
		return viewTypeRegistry.getComponent( viewData.viewType );
	}

	return Infobox;
}

function isLatestRevision(): boolean {
	return mw.config.get( 'wgRevisionId' ) === mw.config.get( 'wgCurRevisionId' );
}

onMounted( async (): Promise<void> => {
	if ( showsOldRevisionOrDiff ) {
		findMainSubjectInFull()?.element.classList.add( 'ext-neowiki-view' );
	}

	const views = collectViews( document.querySelectorAll( '.ext-neowiki-view' ) );
	const storeStateLoader = NeoWikiExtension.getInstance().getStoreStateLoader();

	await Promise.all( [
		storeStateLoader.loadSubjectsAndSchemas(
			new Set( views.map( ( view ) => view.subjectId.text ) ),
			new Set( views.filter( ( view ) => view.layoutName !== undefined ).map( ( view ) => view.subjectId.text ) )
		),
		storeStateLoader.loadLayouts(
			new Set( views.map( ( v ) => v.layoutName ).filter( ( n ): n is string => n !== undefined ) )
		)
	] );

	// Each View is told about the page holding its own Subject, which a View can render from
	// anywhere, so the Subjects have to be loaded before there is a page to ask about.
	viewsData.value = await Promise.all( views.filter( subjectAndSchemaLoaded ).map( withEditPermission ) );
} );

/**
 * A View Type renders the Subject and its Schema from the Pinia stores, so a View is mounted only
 * when both are there: the placeholder of one whose data did not load stays empty while the page's
 * other Views render. The Subject store alone does not settle it, since it can hold a Subject
 * without its Schema: one whose Schema read failed, or the target of another Subject's relations.
 */
function subjectAndSchemaLoaded( view: View ): boolean {
	const subject = useSubjectStore().findSubject( view.subjectId );

	return subject !== undefined && useSchemaStore().schemas.has( subject.getSchemaName() );
}

function findMainSubjectInFull(): { element: HTMLElement; subjectId: string } | null {
	// Wikitext can give an element this id, but not a data-mw-* attribute.
	const element = document.querySelector<HTMLElement>( '#ext-neowiki-main-subject[data-mw-neowiki-subject-id]' );
	const subjectId = element?.dataset.mwNeowikiSubjectId;

	return element === null || subjectId === undefined ? null : { element, subjectId };
}

// eslint-disable-next-line no-undef
function collectViews( elements: NodeListOf<HTMLElement> ): View[] {
	return Array.from( elements )
		.map( ( element ) => toView( element ) )
		.filter( ( view ): view is View => view !== null );
}

function toView( element: HTMLElement ): View|null {
	if ( !element.dataset.mwNeowikiSubjectId ) {
		return null;
	}

	try {
		return {
			subjectId: new SubjectId( element.dataset.mwNeowikiSubjectId ),
			element: element,
			viewType: element.dataset.mwNeowikiViewType,
			layoutName: element.dataset.mwNeowikiLayoutName
		};
	} catch ( error ) {
		console.error( error );
		return null;
	}
}

async function withEditPermission( view: View ): Promise<ViewData> {
	return {
		...view,
		canEditSubject: isLatestRevision() && await canEditSubjectOnItsPage(
			useSubjectStore().findSubject( view.subjectId ),
			subjectPermissionHints
		)
	};
}

</script>
