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
			{{ anotherThing }}
		</p>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd } from '@wikimedia/codex-icons';
import { Neo } from 'neo';

defineProps<{ msg: string }>();

const count = ref( 0 );

const anotherThing = Neo.getInstance().getSomething().getAnotherThing().doAnotherThing();
</script>

<style scoped>
.read-the-docs {
	color: #888;
}
</style>
