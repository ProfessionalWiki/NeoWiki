<template>
	<CdxField
		:status="fieldStatus"
		:messages="fieldMessages"
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
		<NeoNativeInput
			:model-value="internalInputValue"
			:start-icon="startIcon"
			input-type="number"
			@update:model-value="onInput"
			@unparseable-change="onUnparseableChange"
			@wheel.passive="onWheel"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@/domain/Value';
</script>

<script setup lang="ts">
import { ref, toRef, watch } from 'vue';
import { CdxField, CdxIcon } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { newNumberValue, NumberValue, ValueType } from '@/domain/Value';
import { NumberType, NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useFieldServerViolation } from '@/composables/useFieldServerViolation.ts';
import { useUnparseableInput } from '@/composables/useUnparseableInput.ts';
import NeoNativeInput from '@/components/common/NeoNativeInput.vue';

const props = withDefaults(
	defineProps<ValueInputProps<NumberProperty>>(),
	{
		modelValue: () => newNumberValue( NaN ),
		label: ''
	}
);

const startIcon = NeoWikiServices.getComponentRegistry().getIcon( NumberType.typeName );

const emit = defineEmits<ValueInputEmits>();

const { validationMessages, clearServerViolation } = useFieldServerViolation(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	emit
);

const { fieldMessages, fieldStatus, onUnparseableChange, unparseableInputMessage } =
	useUnparseableInput( validationMessages, clearServerViolation );

const internalInputValue = ref<string>( '' );

const initializeInputValue = ( value: Value | undefined ): void => {
	const number = value && value.type === ValueType.Number ? ( value as NumberValue ).number : NaN;
	internalInputValue.value = isNaN( number ) ? '' : number.toString();
};

initializeInputValue( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	initializeInputValue( newValue );
} );

function onInput( newValue: string ): void {
	internalInputValue.value = newValue;
	clearServerViolation();
	emit( 'update:modelValue', newValue === '' ? undefined : newNumberValue( Number( newValue ) ) );
}

// Chromium steps a focused number field with the wheel, even a wheel turned to scroll the form past it.
function onWheel( event: WheelEvent ): void {
	( event.currentTarget as HTMLInputElement ).blur();
}

const isInputEmpty = ( inputString: string ): boolean =>
	inputString === '' || isNaN( Number( inputString ) );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return isInputEmpty( internalInputValue.value ) ? undefined : newNumberValue( Number( internalInputValue.value ) );
	},
	unparseableInputMessage
} );
</script>
