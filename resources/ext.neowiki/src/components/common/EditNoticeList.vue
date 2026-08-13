<template>
	<div v-if="notices.length > 0" class="ext-neowiki-edit-notices">
		<CdxMessage
			v-for="notice in notices"
			:key="notice.key"
			class="ext-neowiki-edit-notices__notice"
			type="notice"
		>
			<!--
				The markup comes from the server: parsed wikitext, which MediaWiki's parser has already
				sanitized, or an extension's own rendering, which the extension is responsible for
				escaping. See docs/extending/extending.md.
			-->
			<!-- eslint-disable-next-line vue/no-v-html -->
			<div v-html="notice.html" />
		</CdxMessage>
	</div>
</template>

<script setup lang="ts">
import { CdxMessage } from '@wikimedia/codex';
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

	// Notices are short by contract, but an admin can write anything, so cap the space one can
	// take rather than letting it push the editor's own controls out of view.
	&__notice {
		max-height: 40vh;
		overflow-y: auto;
	}
}
</style>
