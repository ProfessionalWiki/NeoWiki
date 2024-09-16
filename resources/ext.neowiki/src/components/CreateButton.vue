<template>
	<CdxButton @click="openCreateSubjectDialog">
		<CdxIcon :icon="cdxIconAdd" />
		{{ $i18n( 'neowiki-create-button' ).text() }}
	</CdxButton>
</template>

<script setup lang="ts">
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';

const openCreateSubjectDialog = (): void => {
	alert( 'openCreateSubjectDialog' );
};
</script>
