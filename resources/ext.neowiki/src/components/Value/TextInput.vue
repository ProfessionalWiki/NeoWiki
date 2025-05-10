<template>
	<CdxField
		:is-fieldset="true"
		:messages="fieldMessages"
		:status="fieldMessages.error && !props.property.multiple ? 'error' : 'default'"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ props.label }}
		</template>
		<NeoMultiTextInput
			v-if="props.property.multiple"
			:model-value="displayValues"
			:label="props.label"
			:messages="inputMessages"
			:start-icon="startIcon"
			@update:model-value="onInput"
		/>
		<CdxTextInput
			v-else
			:model-value="displayValues[0] === undefined ? '' : displayValues[0]"
			:start-icon="startIcon"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import NeoMultiTextInput from '@/components/common/NeoMultiTextInput.vue';
import { TextProperty, TextType } from '@neo/domain/propertyTypes/Text.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract';
import { useStringValueInput } from '@/composables/useStringValueInput.ts';

const props = withDefaults(
	defineProps<ValueInputProps<TextProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const {
	displayValues,
	fieldMessages,
	inputMessages,
	startIcon,
	onInput,
	getCurrentValue
} = useStringValueInput( props, emit, TextType.typeName );

defineExpose<ValueInputExposes>( {
	getCurrentValue: getCurrentValue
} );

</script>
