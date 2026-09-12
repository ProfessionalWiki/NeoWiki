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

	<teleport v-if="shouldShowSubjectCreator" to="#mw-content-text">
		<SubjectCreatorDialog :page-has-main-subject="pageHasMainSubject" />
	</teleport>
</template>

<script setup lang="ts">
import type { Component } from 'vue';
import { onMounted, ref } from 'vue';
import { SubjectId } from '@/domain/SubjectId';
import Infobox from '@/components/Views/Infobox.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { useLayoutStore } from '@/stores/LayoutStore.ts';
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { canEditSubjectOnItsPage } from '@/presentation/subjectEditPermission.ts';

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

const shouldShowSubjectCreator = ref( props.showSubjectCreator );
const pageHasMainSubject = ref( props.pageHasMainSubject );
const subjectPermissionHints = NeoWikiServices.getSubjectPermissionHints();
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
	const views = collectViews( document.querySelectorAll( '.ext-neowiki-view' ) );
	const storeStateLoader = NeoWikiExtension.getInstance().getStoreStateLoader();

	await Promise.all( [
		storeStateLoader.loadSubjectsAndSchemas(
			new Set( views.map( ( view ) => view.subjectId.text ) )
		),
		storeStateLoader.loadLayouts(
			new Set( views.map( ( v ) => v.layoutName ).filter( ( n ): n is string => n !== undefined ) )
		)
	] );

	// Each View is told about the page holding its own Subject, which a View can render from
	// anywhere, so the Subjects have to be loaded before there is a page to ask about.
	viewsData.value = await Promise.all( views.map( withEditPermission ) );
} );

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
