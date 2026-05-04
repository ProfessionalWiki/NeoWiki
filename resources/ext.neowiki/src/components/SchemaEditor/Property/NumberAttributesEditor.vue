<template>
	<div class="number-attributes cdx-field">
		<ConstraintAttributesEditor
			:property
			@update:property="onUpdate"
		/>
		<PrecisionInput
			:property
			@update:property="onUpdate"
		/>
	</div>
</template>

<script setup lang="ts">
import type { NumberProperty } from '@/domain/propertyTypes/Number';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract';
import ConstraintAttributesEditor from '@/components/SchemaEditor/Property/ConstraintAttributesEditor.vue';
import PrecisionInput from '@/components/SchemaEditor/Property/PrecisionInput.vue';

defineProps<AttributesEditorProps<NumberProperty>>();
const emit = defineEmits<AttributesEditorEmits<NumberProperty>>();

function onUpdate( partial: Partial<NumberProperty> ): void {
	emit( 'update:property', partial );
}
</script>
