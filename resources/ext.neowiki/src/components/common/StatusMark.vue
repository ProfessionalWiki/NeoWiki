<!-- The mark sits inside a list row's accessible name, which the row takes from its content,
	so it needs text of its own: an error's message, or a label for the dot, which is also its tooltip. -->
<template>
	<CdxIcon
		v-if="status.kind === 'error'"
		class="ext-neowiki-status-mark ext-neowiki-status-mark--error"
		:icon="cdxIconError"
		:icon-label="status.message"
		size="x-small"
	/>
	<span
		v-else
		class="ext-neowiki-status-mark ext-neowiki-status-mark--unsaved"
		role="img"
		:aria-label="$i18n( 'neowiki-subject-editor-unsaved' ).text()"
		:title="$i18n( 'neowiki-subject-editor-unsaved' ).text()"
	/>
</template>

<script setup lang="ts">
import { CdxIcon } from '@wikimedia/codex';
import { cdxIconError } from '@wikimedia/codex-icons';
import type { ItemStatus } from '@/components/common/ItemStatus.ts';

defineProps<{
	status: ItemStatus;
}>();
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-status-mark {
	&--unsaved {
		display: inline-block;
		width: @size-50;
		height: @size-50;
		border-radius: @border-radius-circle;
		/* Drawn as an icon is, in a colour token: dark mode keeps the progressive background token dark, too faint
			on a selected row's background. */
		background-color: @color-progressive;
	}

	&--error {
		color: @color-error;
	}
}
</style>
