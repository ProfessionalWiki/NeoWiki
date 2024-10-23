<template>
	<div />
	<!-- TODO -->
</template>

<script setup lang="ts">
import { MultiStringProperty } from '@neo/domain/PropertyDefinition.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/Editor/Property/AttributesEditorContract.ts';

defineProps<AttributesEditorProps<MultiStringProperty>>();
defineEmits<AttributesEditorEmits<MultiStringProperty>>();

// TODO: emit like in NumberAttributesEditor.vue
</script>
