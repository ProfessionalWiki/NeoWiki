<template>
	<!-- cdx-field class is used for spacing -->
	<!-- Boolean properties have no type-specific attributes. -->
	<div class="boolean-attributes cdx-field" />
</template>

<script setup lang="ts">
import { BooleanProperty } from '@/domain/propertyTypes/Boolean.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';

defineProps<AttributesEditorProps<BooleanProperty>>();
defineEmits<AttributesEditorEmits<BooleanProperty>>();
</script>
