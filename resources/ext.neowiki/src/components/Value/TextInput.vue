<template>
	<BaseMultiStringInput
		v-bind="$props"
		:start-icon="startIcon"
		property-type-name="text"
		input-type="text"
		root-class="neo-text-field"
		@update:model-value="value => onInput( value )"
	/>
</template>

<script lang="ts">
import { Value } from '@neo/domain/Value.ts';
</script>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import BaseMultiStringInput from '@/components/Value/BaseMultiStringInput.vue';
import { TextProperty, TextType } from '@neo/domain/propertyTypes/Text.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract';
import { newStringValue, ValueType, StringValue } from '@neo/domain/Value.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
const props = withDefaults(
	defineProps<ValueInputProps<TextProperty>>(),
	{
		modelValue: () => newStringValue( '' ),
		label: ''
	}
);

const startIcon = computed( () => NeoWikiServices.getComponentRegistry().getIcon( TextType.typeName ) );

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
