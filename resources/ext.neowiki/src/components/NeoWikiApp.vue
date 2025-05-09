<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<teleport
		v-for="view in viewsData"
		:key="`view-${view.id}`"
		:to="view.element"
	>
		<AutomaticInfobox
			v-if="view.type === 'infobox'"
			:subject-id="view.subjectId"
			:can-edit-subject="view.canEditSubject"
		/>
	</teleport>

	<teleport v-if="canCreateSubject" to="#mw-indicator-neowiki-create-button">
		<!-- TODO: reimplement or remove -->
	</teleport>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import { SubjectId } from '@neo/domain/SubjectId';
import AutomaticInfobox from '@/components/Views/AutomaticInfobox.vue';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';

interface ViewData {
	id: string;
	type: string;
	element: HTMLElement;
	subjectId: SubjectId;
	canEditSubject: boolean;
}

const viewsData = ref<ViewData[]>( [] );

const canCreateSubject = ref( false );
const subjectAuthorizer = NeoWikiServices.getSubjectAuthorizer();

onMounted( async (): Promise<void> => {
	// TODO: This should look for a generic class for views, not just infoboxes.
	const localViewsData = await getViewsData( document.querySelectorAll( '.neowiki-infobox[data-mw-neowiki-view-type]' ) );

	await NeoWikiExtension.getInstance().getStoreStateLoader().loadSubjectsAndSchemas(
		new Set( localViewsData.map( ( viewData ) => viewData.subjectId.text ) )
	);

	viewsData.value = localViewsData;

	canCreateSubject.value = document.querySelector( '#mw-indicator-neowiki-create-button' ) !== null;
} );

// eslint-disable-next-line no-undef
async function getViewsData( elements: NodeListOf<Element> ): Promise<ViewData[]> {
	const viewsData: ViewData[] = [];

	for ( const element of elements ) {
		if ( !( element instanceof HTMLElement ) ) {
			continue;
		}

		const viewData = await getViewData( element );
		if ( viewData ) {
			viewsData.push( viewData );
		}
	}
	return viewsData;
}

async function getViewData( element: HTMLElement ): Promise<ViewData|null> {
	if ( !element.dataset.mwNeowikiViewType || !element.dataset.mwSubjectId ) {
		return null;
	}

	try {
		const subjectId = new SubjectId( element.dataset.mwSubjectId );
		return {
			id: subjectId.text,
			type: element.dataset.mwNeowikiViewType,
			element: element,
			subjectId: subjectId,
			canEditSubject: await subjectAuthorizer.canEditSubject( subjectId )
		};
	} catch ( error ) {
		console.error( error );
		return null;
	}
}

</script>
