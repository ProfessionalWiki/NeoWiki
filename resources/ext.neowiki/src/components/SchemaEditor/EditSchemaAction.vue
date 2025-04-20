<template>
	<div>
		<SchemaEditor />

		<CdxButton action="progressive" weight="primary">
			<CdxIcon :icon="cdxIconCheck" />
			{{ $i18n( 'neowiki-save-schema' ).text() }}
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';
</script>
