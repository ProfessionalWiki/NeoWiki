<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<teleport
		v-for="view in viewsData"
		:key="`view-${view.id}`"
		:to="view.element"
	>
		<component
			:is="resolveViewComponent( view.viewType )"
			:subject-id="view.subjectId"
			:can-edit-subject="view.canEditSubject"
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
import AutomaticInfobox from '@/components/Views/AutomaticInfobox.vue';
import SubjectCreatorDialog from '@/components/SubjectCreator/SubjectCreatorDialog.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

interface ViewData {
	id: string;
	element: HTMLElement;
	subjectId: SubjectId;
	canEditSubject: boolean;
	viewType?: string;
}

const props = defineProps<{
	showSubjectCreator: boolean;
}>();

const viewsData = ref<ViewData[]>( [] );

const shouldShowSubjectCreator = ref( props.showSubjectCreator );
const subjectAuthorizer = NeoWikiServices.getSubjectAuthorizer();
const viewTypeRegistry = NeoWikiServices.getViewTypeRegistry();

function resolveViewComponent( viewType?: string ): Component {
	if ( viewType !== undefined && viewTypeRegistry.hasType( viewType ) ) {
		return viewTypeRegistry.getComponent( viewType );
	}
	return AutomaticInfobox;
}

function isLatestRevision(): boolean {
	return mw.config.get( 'wgRevisionId' ) === mw.config.get( 'wgCurRevisionId' );
}

onMounted( async (): Promise<void> => {
	const localViewsData = await getViewsData( document.querySelectorAll( '.ext-neowiki-view' ) );

	await NeoWikiExtension.getInstance().getStoreStateLoader().loadSubjectsAndSchemas(
		new Set( localViewsData.map( ( viewData ) => viewData.subjectId.text ) )
	);

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
			viewType: element.dataset.mwNeowikiViewType
		};
	} catch ( error ) {
		console.error( error );
		return null;
	}
}

</script>
