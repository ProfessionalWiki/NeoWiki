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
			:component-registry="componentRegistry"
			:can-edit="infobox.canEdit"
		/>
	</teleport>

	<teleport to="#mw-indicator-neowiki-create-button">
		<CreateSubjectButton />
	</teleport>
</template>

<script setup lang="ts">
import { ref, onMounted, computed } from 'vue';
import { useSubjectStore } from '@/stores/SubjectStore';
import { SubjectId } from '@neo/domain/SubjectId';
import { Subject } from '@neo/domain/Subject';
import AutomaticInfobox from '@/components/AutomaticInfobox/AutomaticInfobox.vue';
import CreateSubjectButton from '@/components/CreateSubject/CreateSubjectButton.vue';
import { NeoWikiExtension } from '@/NeoWikiExtension.ts';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@neo/domain/Schema.ts';

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

const componentRegistry = computed( () => NeoWikiExtension.getInstance().getFormatSpecificComponentRegistry() );

onMounted( async (): Promise<void> => {
	const elements = Array.from( document.querySelectorAll( '.neowiki-infobox' ) );

	infoboxData.value = ( await Promise.all(
		elements.map( async ( element ): Promise<InfoboxData> => {
			const subjectId = element.getAttribute( 'data-subject-id' )!;
			const subject = getSubject( subjectId );

			return {
				id: subjectId,
				element,
				subject: subject,
				schema: getSchema( subject.getSchemaName() ),
				canEdit: await canEdit( subjectId )
			};
		} )
	) );
} );

function getSubject( subjectId: string ): Subject {
	return subjectStore.getSubject( new SubjectId( subjectId ) );
}

function getSchema( schemaName: string ): Schema {
	return schemaStore.getSchema( schemaName );
}

async function canEdit( subjectId: string ): Promise<boolean> {
	return await NeoWikiExtension.getInstance().newSubjectAuthorizer().canEditSubject( new SubjectId( subjectId ) );
}
</script>
