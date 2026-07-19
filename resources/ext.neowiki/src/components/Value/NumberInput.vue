<template>
	<CdxField
		:status="validationError === null ? 'default' : 'error'"
		:messages="validationError === null ? {} : { error: validationError }"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ label }}
			<CdxIcon
				v-if="props.property.description"
				v-tooltip="props.property.description"
				:icon="cdxIconInfo"
				class="ext-neowiki-value-input__description-icon"
				size="small"
			/>
		</template>
		<CdxTextInput
			:model-value="internalInputValue"
			:start-icon="startIcon"
			input-type="number"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@/domain/Value';
</script>

<script setup lang="ts">
import { ref, toRef, watch } from 'vue';
import { CdxField, CdxIcon, CdxTextInput } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { newNumberValue, NumberValue, ValueType } from '@/domain/Value';
import { NumberType, NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useFieldServerViolation } from '@/composables/useFieldServerViolation.ts';

const props = withDefaults(
	defineProps<ValueInputProps<NumberProperty>>(),
	{
		modelValue: () => newNumberValue( NaN ),
		label: ''
	}
);

const startIcon = NeoWikiServices.getComponentRegistry().getIcon( NumberType.typeName );

const emit = defineEmits<ValueInputEmits>();

const { validationError, clearServerViolation } = useFieldServerViolation(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	emit
);

const internalInputValue = ref<string>( '' );

const initializeInputValue = ( value: Value | undefined ): void => {
	if ( value && value.type === ValueType.Number ) {
		const num = ( value as NumberValue ).number;
		internalInputValue.value = isNaN( num ) ? '' : num.toString();
	} else {
		internalInputValue.value = '';
	}
};

initializeInputValue( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	initializeInputValue( newValue );
} );

function onInput( newValue: string ): void {
	internalInputValue.value = newValue;
	const value = newValue === '' ? undefined : newNumberValue( Number( newValue ) );
	emit( 'update:modelValue', value );
	clearServerViolation();
}

const isInputEmpty = ( inputString: string ): boolean =>
	inputString === '' || isNaN( Number( inputString ) );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return isInputEmpty( internalInputValue.value ) ? undefined : newNumberValue( Number( internalInputValue.value ) );
	}
} );
</script>
