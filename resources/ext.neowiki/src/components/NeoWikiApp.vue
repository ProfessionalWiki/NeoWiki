<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<teleport
		v-for="view in viewsData"
		:key="`view-${view.id}`"
		:to="view.element"
	>
		<!-- TODO: Implement other views -->
		<AutomaticInfobox
			:subject-id="view.subjectId"
			:can-edit-subject="view.canEditSubject"
		/>
	</teleport>

	<teleport v-if="shouldShowSubjectCreator" to="#mw-content-text">
		<SubjectCreatorDialog />
	</teleport>
</template>

<script setup lang="ts">
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
}

const props = defineProps<{
	showSubjectCreator: boolean;
}>();

const viewsData = ref<ViewData[]>( [] );

const shouldShowSubjectCreator = ref( props.showSubjectCreator );
const subjectAuthorizer = NeoWikiServices.getSubjectAuthorizer();

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
	if ( !element.dataset.subjectId ) {
		return null;
	}

	try {
		const subjectId = new SubjectId( element.dataset.subjectId );
		return {
			id: subjectId.text,
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
