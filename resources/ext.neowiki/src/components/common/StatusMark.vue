<!-- Seen, not read: the row says its status in words after its own text, where a screen reader reaches it after the
	row's name. The tooltip says it for the pointer. -->
<template>
	<CdxIcon
		v-if="status.kind === 'error'"
		class="ext-neowiki-status-mark ext-neowiki-status-mark--error"
		:icon="cdxIconError"
		:title="statusText( status )"
		size="x-small"
		aria-hidden="true"
	/>
	<span
		v-else
		class="ext-neowiki-status-mark ext-neowiki-status-mark--unsaved"
		:title="statusText( status )"
		aria-hidden="true"
	/>
</template>

<script setup lang="ts">
import { CdxIcon } from '@wikimedia/codex';
import { cdxIconError } from '@wikimedia/codex-icons';
import { statusText, type ItemStatus } from '@/components/common/ItemStatus.ts';

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

	/* Compounded with Codex's own class, whose colour would otherwise win where its stylesheet loads later. */
	&--error.cdx-icon {
		color: @color-error;
	}
}
</style>
