<template>
	<CdxButton>
		<CdxIcon :icon="cdxIconEdit" />
		{{ $i18n( 'neowiki-edit-schema' ).text() }}
	</CdxButton>
</template>

<script setup lang="ts">
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconEdit } from '@wikimedia/codex-icons';
</script>
