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
import { useSubjectStore } from '@/stores/SubjectStore.ts';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

interface InfoboxData {
	id: string;
	element: Element;
	subjectId: SubjectId;
	canEditSubject: boolean;
}

const infoboxData = ref<InfoboxData[]>( [] );

const canCreateSubject = ref( false );
const subjectAuthorizer = NeoWikiServices.getSubjectAuthorizer();

async function loadSubjectsIntoStore(): Promise<void> { // TODO: extract service
	const elements = Array.from( document.querySelectorAll( '.neowiki-infobox' ) );
	const subjectIds = elements.map( ( element ) => new SubjectId( element.getAttribute( 'data-subject-id' )! ) );
	const subjectStore = useSubjectStore();
	const schemaStore = useSchemaStore();

	await Promise.all( subjectIds.map( async ( subjectId ): Promise<void> => {
		const subject = await NeoWikiExtension.getInstance().getSubjectRepository().getSubject( subjectId );

		if ( subject !== undefined ) {
			subjectStore.setSubject( subject.getId(), subject );
		}

		const schema = await NeoWikiExtension.getInstance().getSchemaRepository().getSchema( subject.getSchemaName() );
		schemaStore.setSchema( subject.getSchemaName(), schema );
	} ) );
}

onMounted( async (): Promise<void> => {
	await loadSubjectsIntoStore();

	const elements = Array.from( document.querySelectorAll( '.neowiki-infobox' ) );

	infoboxData.value = ( await Promise.all(
		elements.map( async ( element ): Promise<InfoboxData> => {
			const subjectId = element.getAttribute( 'data-subject-id' )!;

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
