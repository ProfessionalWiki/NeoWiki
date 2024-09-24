<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<teleport
		v-for="( el, index ) in infoboxElements"
		:key="`infobox-${index}`"
		:to="el"
	>
		<AutomaticInfobox
			v-if="getSubject( el.getAttribute( 'data-subject-id' ) )"
			:subject="getSubject( el.getAttribute( 'data-subject-id' ) )!"
			:schema="getSchema( getSubject( el.getAttribute( 'data-subject-id' ) )!.getSchemaName() )"
			:value-format-component-registry="NeoWikiExtension.getInstance().getValueFormatComponentRegistry()"
		/>
	</teleport>

	<teleport to="#mw-indicator-neowiki-create-button">
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
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema } from '@neo/domain/Schema.ts';

const infoboxElements = ref<Element[]>( [] );
const subjectStore = useSubjectStore();
const schemaStore = useSchemaStore();

onMounted( () => {
	infoboxElements.value = Array.from( document.querySelectorAll( '.neowiki-infobox' ) );
} );

function getSubject( subjectId: string | null ): Subject | null {
	if ( !subjectId ) {
		return null;
	}

	return subjectStore.getSubject( new SubjectId( subjectId ) );
}

function getSchema( schemaName: string ): Schema {
	return schemaStore.getSchema( schemaName );
}
</script>
