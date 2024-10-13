<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<teleport
		v-for="infobox in infoboxData"
		:key="`infobox-${infobox.id}`"
		:to="infobox.element"
	>
		<AutomaticInfobox
			:subject="infobox.subject as Subject"
			:schema="infobox.schema as Schema"
			:can-edit-subject="infobox.canEditSubject"
		/>
	</teleport>

	<teleport v-if="canCreateSubject" to="#mw-indicator-neowiki-create-button">
		<CreateSubjectButton />
	</teleport>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import { useSubjectStore } from '@/stores/SubjectStore';
import { SubjectId } from '@neo/domain/SubjectId';
import { Subject } from '@neo/domain/Subject';
import AutomaticInfobox from '@/components/AutomaticInfobox.vue';
import CreateSubjectButton from '@/components/CreateSubjectButton.vue';
import { Schema } from '@neo/domain/Schema.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

interface InfoboxData {
	id: string;
	element: Element;
	subject: Subject;
	schema: Schema;
	canEditSubject: boolean;
}

const infoboxData = ref<InfoboxData[]>( [] );
const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();

const canCreateSubject = ref( false );
const subjectAuthorizer = NeoWikiServices.getSubjectAuthorizer();

onMounted( async (): Promise<void> => {
	const elements = Array.from( document.querySelectorAll( '.neowiki-infobox' ) );

	infoboxData.value = ( await Promise.all(
		elements.map( async ( element ): Promise<InfoboxData> => {
			const subjectId = element.getAttribute( 'data-subject-id' )!;
			const subject = await subjectStore.getOrFetchSubject( new SubjectId( subjectId ) );
			// TODO: handle schema not found

			return {
				id: subjectId,
				element,
				subject: subject,
				schema: await schemaStore.getOrFetchSchema( subject.getSchemaName() ),
				canEditSubject: await subjectAuthorizer.canEditSubject( new SubjectId( subjectId ) )
			};
		} )
	) );

	canCreateSubject.value = document.querySelector( '#mw-indicator-neowiki-create-button' ) !== null;
} );
</script>
