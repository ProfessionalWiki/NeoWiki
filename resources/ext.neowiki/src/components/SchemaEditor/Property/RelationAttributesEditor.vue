<template>
	<!-- cdx-field class is used for spacing -->
	<div class="relation-attributes cdx-field">
		TODO
	</div>
</template>

<script setup lang="ts">
import { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';

defineProps<AttributesEditorProps<RelationProperty>>();
defineEmits<AttributesEditorEmits<RelationProperty>>();
</script>
