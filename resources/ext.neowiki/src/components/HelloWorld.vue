<template>
	<div>
		<h1>{{ $i18n( 'neowiki-name' ).text() }}: {{ msg }}</h1>

		<CdxButton
			action="progressive"
			@click="count++"
		>
			<CdxIcon :icon="cdxIconAdd" />
			count is {{ count }}
		</CdxButton>

		<p class="read-the-docs">
			Click on the Vite and Vue logos to learn more
		</p>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';

defineProps<{ msg: string }>();

const count = ref( 0 );
</script>

<style scoped>
.read-the-docs {
	color: #888;
}
</style>
