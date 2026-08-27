<!-- The dot sits inside a tree node's accessible name, which the node takes from its content,
	so it needs text of its own: `role="img"` with a label, and a title for the pointer. -->
<template>
	<span
		class="ext-neowiki-unsaved-dot"
		role="img"
		:aria-label="$i18n( 'neowiki-subject-editor-unsaved' ).text()"
		:title="$i18n( 'neowiki-subject-editor-unsaved' ).text()"
	/>
</template>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-unsaved-dot {
	flex: none;
	display: inline-block;
	width: @size-50;
	height: @size-50;
	border-radius: @border-radius-circle;
	background-color: @color-warning;
}
</style>
