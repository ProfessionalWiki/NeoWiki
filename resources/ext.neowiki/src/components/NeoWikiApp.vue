<!-- eslint-disable vue/no-multiple-template-root -->
<template>
	<teleport
		v-for="( el, index ) in infoboxElements"
		:key="`infobox-${index}`"
		:to="el">
		<!-- TODO: Remove dummy data -->
		<AutomaticInfobox
			title="Foo"
			:statements="[
				{ property: 'Foo', value: 'Bar' },
				{ property: 'Lorem', value: 'Ipsum' }
			]"
		/>
	</teleport>

	<teleport to="#mw-indicator-neowiki-create-button">
		<CreateSubjectButton />
	</teleport>
</template>

<script setup lang="ts">
import { ref, onMounted } from 'vue';
import AutomaticInfobox from '@/components/AutomaticInfobox.vue';
import CreateSubjectButton from '@/components/CreateSubject/CreateSubjectButton.vue';

const infoboxElements = ref<Element[]>( [] );

onMounted( () => {
	infoboxElements.value = Array.from( document.querySelectorAll( '.neowiki-infobox' ) );
} );
</script>
