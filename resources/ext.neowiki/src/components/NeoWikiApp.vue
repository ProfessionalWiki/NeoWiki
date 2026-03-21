<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<teleport
		v-for="view in viewsData"
		:key="`view-${view.id}`"
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
		<SubjectCreatorDialog />
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

interface ViewData {
	id: string;
	element: HTMLElement;
	subjectId: SubjectId;
	canEditSubject: boolean;
	viewType?: string;
	layoutName?: string;
}

const props = defineProps<{
	showSubjectCreator: boolean;
}>();

const viewsData = ref<ViewData[]>( [] );

const shouldShowSubjectCreator = ref( props.showSubjectCreator );
const subjectAuthorizer = NeoWikiServices.getSubjectAuthorizer();
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
	const localViewsData = await getViewsData( document.querySelectorAll( '.ext-neowiki-view' ) );
	const storeStateLoader = NeoWikiExtension.getInstance().getStoreStateLoader();

	await Promise.all( [
		storeStateLoader.loadSubjectsAndSchemas(
			new Set( localViewsData.map( ( viewData ) => viewData.subjectId.text ) )
		),
		storeStateLoader.loadLayouts(
			new Set( localViewsData.map( ( v ) => v.layoutName ).filter( ( n ): n is string => n !== undefined ) )
		)
	] );

	viewsData.value = localViewsData;
} );

// eslint-disable-next-line no-undef
async function getViewsData( elements: NodeListOf<HTMLElement> ): Promise<ViewData[]> {
	const viewsData: ViewData[] = [];

	for ( const element of elements ) {
		const viewData = await getViewData( element );
		if ( viewData ) {
			viewsData.push( viewData );
		}
	}
	return viewsData;
}

async function getViewData( element: HTMLElement ): Promise<ViewData|null> {
	if ( !element.dataset.mwNeowikiSubjectId ) {
		return null;
	}

	try {
		const subjectId = new SubjectId( element.dataset.mwNeowikiSubjectId );
		return {
			id: subjectId.text,
			element: element,
			subjectId: subjectId,
			canEditSubject: isLatestRevision() && await subjectAuthorizer.canEditSubject( subjectId ),
			viewType: element.dataset.mwNeowikiViewType,
			layoutName: element.dataset.mwNeowikiLayoutName
		};
	} catch ( error ) {
		console.error( error );
		return null;
	}
}

</script>
