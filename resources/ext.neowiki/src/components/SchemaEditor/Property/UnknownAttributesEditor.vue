<template>
	<p class="ext-neowiki-unknown-attributes-editor">
		{{ note }}
	</p>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';

const props = defineProps<AttributesEditorProps<PropertyDefinition>>();

const note = computed( (): string => mw.msg( 'neowiki-property-type-unknown-note', props.property.type ) );
</script>

<style lang="less">
@import ( reference ) '@wikimedia/codex-design-tokens/theme-wikimedia-ui.less';

.ext-neowiki-unknown-attributes-editor {
	color: @color-subtle;
	font-size: @font-size-small;
}
</style>
