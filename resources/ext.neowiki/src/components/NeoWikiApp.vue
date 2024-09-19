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
import AutomaticInfobox from '@/components/AutomaticInfobox.vue';
import CreateSubjectButton from '@/components/CreateSubject/CreateSubjectButton.vue';

const infoboxElements = ref<Element[]>( [] );
const subjectStore = useSubjectStore();

onMounted( () => {
	infoboxElements.value = Array.from( document.querySelectorAll( '.neowiki-infobox' ) );
} );

function getSubject( subjectId: string | null ): Subject | null {
	if ( !subjectId ) {
		return null;
	}

	return subjectStore.getSubject( new SubjectId( subjectId ) );
}
</script>
