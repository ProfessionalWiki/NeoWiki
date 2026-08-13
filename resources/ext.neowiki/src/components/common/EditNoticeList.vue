<template>
	<div v-if="notices.length > 0" class="ext-neowiki-edit-notices">
		<!--
			A bare container per notice, the way core wraps its own edit notices: whoever wrote the
			notice decides how it looks, so imposing a message component here would nest a box inside
			their box and add an icon they did not choose. The class and data attribute are hooks for
			styling a notice, not styling of their own.

			The markup comes from the server: parsed wikitext, which MediaWiki's parser has already
			sanitized, or an extension's own rendering, which the extension is responsible for
			escaping. See docs/extending/extending.md.
		-->
		<!-- eslint-disable vue/no-v-html -->
		<div
			v-for="notice in notices"
			:key="notice.key"
			class="ext-neowiki-edit-notice"
			:data-mw-neowiki-editnotice-key="notice.key"
			v-html="notice.html"
		/>
		<!-- eslint-enable vue/no-v-html -->
	</div>
</template>

<script setup lang="ts">
import type { EditNotice } from '@/domain/EditNotice';

defineProps<{
	notices: EditNotice[];
}>();
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-edit-notices {
	display: flex;
	flex-direction: column;
	gap: @spacing-50;
	margin-bottom: @spacing-100;

	// Layout hygiene rather than presentation: an editor is a bounded dialog, so a long notice
	// scrolls instead of pushing the fields and the save button out of reach.
	max-height: 40vh;
	overflow-y: auto;
}
</style>
