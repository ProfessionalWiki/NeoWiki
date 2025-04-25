<template>
	<BaseMultiStringInput
		v-bind="$props"
		property-type-name="url"
		input-type="url"
		root-class="neo-url-field"
		:start-icon="cdxIconLink"
		@update:model-value="value => onInput( value )"
	/>
</template>

<script lang="ts">
import type { Value } from '@neo/domain/Value.ts';
</script>

<script setup lang="ts">
import { ref, watch } from 'vue';
import BaseMultiStringInput from '@/components/Value/BaseMultiStringInput.vue';
import { cdxIconLink } from '@wikimedia/codex-icons';
import { UrlProperty } from '@neo/domain/propertyTypes/Url.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract';
import { newStringValue, ValueType, StringValue } from '@neo/domain/Value.ts';

const props = withDefaults(
	defineProps<ValueInputProps<UrlProperty>>(),
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

const isValueEmpty = ( val: Value | undefined ): boolean =>
	!val || ( val.type === ValueType.String && ( val as StringValue ).strings.length === 0 );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return isValueEmpty( internalValue.value ) ? undefined : internalValue.value;
	}
} );
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

// Only URL-specific styles
.neo-url-field {
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

		.cdx-text-input {
			&__input {
				padding-left: 36px;
			}

			&__start-icon {
				left: 8px;
				color: $color-subtle;
			}
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
