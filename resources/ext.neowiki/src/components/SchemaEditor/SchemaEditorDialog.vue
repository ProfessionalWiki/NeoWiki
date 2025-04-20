<template>
	<div>
		<CdxDialog title="TODO">
			<SchemaEditor />
			<!-- buttons -->
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import { CdxDialog } from '@wikimedia/codex';
</script>
