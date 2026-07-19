<template>
	<p class="ext-neowiki-unregistered-type-attributes-editor">
		{{ note }}
	</p>
</template>

<script setup lang="ts">
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';

defineProps<AttributesEditorProps<PropertyDefinition>>();

// The type-name note is deliberately not repeated here: the initial-value input
// below it already names the type. This note only covers the type-specific
// settings, which are preserved via the raw JSON keys but have no editor.
const note = mw.msg( 'neowiki-property-type-unregistered-attributes-note' );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-unregistered-type-attributes-editor {
	color: @color-subtle;
	font-size: @font-size-small;
}
</style>
