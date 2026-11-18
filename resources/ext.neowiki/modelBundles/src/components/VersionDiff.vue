<template>
	<div class="ext-neowiki-version-diff">
		<p class="ext-neowiki-version-diff__legend">
			<span class="ext-neowiki-version-diff__line--removed">− {{ $i18n( 'neowiki-bundle-diff-here' ).text() }}</span>
			<span class="ext-neowiki-version-diff__line--added">+ {{ $i18n( 'neowiki-bundle-diff-upstream' ).text() }}</span>
		</p>
		<pre
			v-for="( hunk, hunkIndex ) in hunks"
			:key="hunkIndex"
			class="ext-neowiki-version-diff__lines"
		><span
			v-for="( line, index ) in hunk"
			:key="index"
			class="ext-neowiki-version-diff__line"
			:class="`ext-neowiki-version-diff__line--${ line.change }`"
		>{{ MARKS[ line.change ] }} {{ line.text }}</span></pre>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { diffHunks, lineDiff } from '../presentation/lineDiff.ts';

const props = defineProps<{
	here: string;
	upstream: string;
}>();

const MARKS = { kept: ' ', removed: '−', added: '+' } as const;

// Lines of context around each change, so that a change deep in a long item shows without scrolling.
const CONTEXT_LINES = 3;

const hunks = computed( () => diffHunks( lineDiff( props.here, props.upstream ), CONTEXT_LINES ) );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-version-diff {
	&__legend {
		display: flex;
		gap: @spacing-100;
		margin: @spacing-50 0 @spacing-25;
		font-size: @font-size-small;
	}

	&__lines {
		max-height: @size-2400;
		overflow: auto;
		margin: 0 0 @spacing-50;
		padding: @spacing-50;
		border: @border-base;
		border-radius: @border-radius-base;
		font-size: @font-size-small;
		white-space: pre-wrap;
	}

	&__lines &__line {
		display: block;
	}

	&__line--removed {
		background-color: @background-color-destructive-subtle;
	}

	&__line--added {
		background-color: @background-color-success-subtle;
	}
}
</style>
