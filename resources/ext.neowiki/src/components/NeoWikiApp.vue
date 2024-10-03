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
			:can-edit="infobox.canEdit"
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
import AutomaticInfobox from '@/components/AutomaticInfobox/AutomaticInfobox.vue';
import CreateSubjectButton from '@/components/CreateSubject/CreateSubjectButton.vue';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { Schema } from '@neo/domain/Schema.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';

interface InfoboxData {
	id: string;
	element: Element;
	subject: Subject;
	schema: Schema;
	canEdit: boolean;
}

const infoboxData = ref<InfoboxData[]>( [] );
const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();

const canCreateSubject = ref( false );

onMounted( async (): Promise<void> => {
	const elements = Array.from( document.querySelectorAll( '.neowiki-infobox' ) );

	infoboxData.value = ( await Promise.all(
		elements.map( async ( element ): Promise<InfoboxData> => {
			const subjectId = element.getAttribute( 'data-subject-id' )!;
			const subject = getSubject( subjectId );
			await schemaStore.fetchSchema( subject.getSchemaName() );
			// TODO: handle schema not found

			return {
				id: subjectId,
				element,
				subject: subject,
				schema: schemaStore.getSchema( subject.getSchemaName() ),
				canEdit: await canEdit( subjectId )
			};
		} )
	) );

	canCreateSubject.value = document.querySelector( '#mw-indicator-neowiki-create-button' ) !== null;
} );

function getSubject( subjectId: string ): Subject {
	return subjectStore.getSubject( new SubjectId( subjectId ) );
}

async function canEdit( subjectId: string ): Promise<boolean> {
	return await NeoWikiExtension.getInstance().newSubjectAuthorizer().canEditSubject( new SubjectId( subjectId ) );
}
</script>
