<template>
	<SubjectPicker
		ref="pickerRef"
		:selected="null"
		:aria-label="$i18n( 'neowiki-special-subject-picker-label' ).text()"
		@update:selected="showSubject"
	/>
</template>

<script setup lang="ts">
import { onMounted, ref } from 'vue';
import SubjectPicker from '@/components/common/SubjectPicker.vue';
import { subjectPageUrl } from '@/presentation/subjectPageUrl.ts';

const pickerRef = ref<InstanceType<typeof SubjectPicker> | null>( null );

// The field is the page's only control, so it takes the focus as Special:Search's does.
onMounted( () => pickerRef.value?.focus() );

function showSubject( subjectId: string | null ): void {
	// Emptying the field selects nothing, which names no page to go to.
	if ( subjectId !== null ) {
		window.location.href = subjectPageUrl( subjectId );
	}
}
</script>
