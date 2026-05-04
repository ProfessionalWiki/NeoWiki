<template>
	<div class="relation-attributes cdx-field">
		<ConstraintAttributesEditor
			:property
			@update:property="onUpdate"
		/>
		TODO
	</div>
</template>

<script setup lang="ts">
import { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import ConstraintAttributesEditor from '@/components/SchemaEditor/Property/ConstraintAttributesEditor.vue';

defineProps<AttributesEditorProps<RelationProperty>>();
const emit = defineEmits<AttributesEditorEmits<RelationProperty>>();

function onUpdate( partial: Partial<RelationProperty> ): void {
	emit( 'update:property', partial );
}
</script>
