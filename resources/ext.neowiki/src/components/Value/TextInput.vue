<template>
	<CdxField
		:is-fieldset="true"
		:messages="fieldMessages"
		:status="fieldMessages.error && !props.property.multiple ? 'error' : 'default'"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ props.label }}
			<CdxIcon
				v-if="props.property.description"
				v-tooltip="props.property.description"
				:icon="cdxIconInfo"
				class="ext-neowiki-value-input__description-icon"
				size="small"
			/>
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
import { CdxField, CdxIcon, CdxTextInput } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { toRef } from 'vue';
import NeoMultiTextInput from '@/components/common/NeoMultiTextInput.vue';
import { TextProperty, TextType } from '@/domain/propertyTypes/Text.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract';
import { useStringValueInput } from '@/composables/useStringValueInput.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
const props = withDefaults(
	defineProps<ValueInputProps<TextProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( TextType.typeName );

const {
	displayValues,
	fieldMessages,
	inputMessages,
	startIcon,
	onInput,
	getCurrentValue
} = useStringValueInput( toRef( props, 'modelValue' ), toRef( props, 'property' ), emit, propertyType );

defineExpose<ValueInputExposes>( {
	getCurrentValue: getCurrentValue
} );

</script>
