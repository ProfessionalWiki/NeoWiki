<template>
	<BaseMultiStringInput
		v-bind="$props"
		property-type-name="text"
		input-type="text"
		root-class="neo-text-field"
		@update:model-value="value => onInput( value )"
	/>
</template>

<script lang="ts">
import { Value } from '@neo/domain/Value.ts';

export interface TextInputExposed {
	getCurrentValue(): Value | undefined;
}
</script>

<script setup lang="ts">
import { ref, watch } from 'vue';
import BaseMultiStringInput from '@/components/Value/BaseMultiStringInput.vue';
import { TextProperty } from '@neo/domain/propertyTypes/Text.ts';
import { ValueInputEmits, ValueInputProps } from '@/components/Value/ValueInputContract';
import { newStringValue } from '@neo/domain/Value.ts';

const props = withDefaults(
	defineProps<ValueInputProps<TextProperty>>(),
	{
		modelValue: () => newStringValue( '' ),
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const internalValue = ref<Value | undefined>( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	internalValue.value = newValue;
} );

function onInput( value: Value | undefined ): void {
	internalValue.value = value;
	emit( 'update:modelValue', value );
}

const getCurrentValue = (): Value | undefined => internalValue.value;

defineExpose<TextInputExposed>( {
	getCurrentValue
} );
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

// Only text-specific styles
.neo-text-field {
	label {
		font-weight: bold;
	}

	&__input-wrapper {
		display: flex;
		align-items: center;
		margin-bottom: 8px;

		.cdx-field {
			flex: 0 0 100%;
		}
	}

	&__add-button {
		float: right;
		margin-top: 8px;

		.cdx-icon {
			color: $color-success;
		}
	}
}
</style>
