<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<teleport
		v-for="infobox in infoboxData"
		:key="`infobox-${infobox.id}`"
		:to="infobox.element"
	>
		<AutomaticInfobox
			:subject-id="infobox.subjectId"
			:can-edit-subject="infobox.canEditSubject"
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

interface InfoboxData {
	id: string;
	element: Element;
	subjectId: SubjectId;
	canEditSubject: boolean;
}

const infoboxData = ref<InfoboxData[]>( [] );

const canCreateSubject = ref( false );
const subjectAuthorizer = NeoWikiServices.getSubjectAuthorizer();

onMounted( async (): Promise<void> => {
	const elements = Array.from( document.querySelectorAll( '.neowiki-infobox' ) );

	await NeoWikiExtension.getInstance().getStoreStateLoader().loadSubjectsAndSchemas(
		new Set( elements.map( ( element ) => element.getAttribute( 'data-mw-subject-id' )! ) )
	);

	infoboxData.value = ( await Promise.all(
		elements.map( async ( element ): Promise<InfoboxData> => {
			const subjectId = element.getAttribute( 'data-mw-subject-id' )!;

			return {
				id: subjectId,
				element,
				subjectId: new SubjectId( subjectId ),
				canEditSubject: await subjectAuthorizer.canEditSubject( new SubjectId( subjectId ) )
			};
		} )
	) );

	canCreateSubject.value = document.querySelector( '#mw-indicator-neowiki-create-button' ) !== null;
} );
</script>
