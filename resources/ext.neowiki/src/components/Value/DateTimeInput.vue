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
			input-type="datetime-local"
			:start-icon="cdxIconClock"
			:model-value="internalInputValue"
			:min="toLocalInputValue( props.property.minimum )"
			:max="toLocalInputValue( props.property.maximum )"
			@update:model-value="onInput"
			@unparseable-change="onUnparseableChange"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@/domain/Value';
</script>

<script setup lang="ts">
import { ref, shallowRef, toRef, watch } from 'vue';
import { CdxField, CdxIcon } from '@wikimedia/codex';
import { cdxIconInfo, cdxIconClock } from '@wikimedia/codex-icons';
import { newStringValue, StringValue, ValueType } from '@/domain/Value';
import { DateTimeProperty, formatDateTimeForDisplay } from '@/domain/propertyTypes/DateTime.ts';
import { fromLocalInputValue, toLocalInputValue } from '@/domain/propertyTypes/dateTimeConversion.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { useFieldServerViolation } from '@/composables/useFieldServerViolation.ts';
import { useUnparseableInput } from '@/composables/useUnparseableInput.ts';
import NeoNativeInput from '@/components/common/NeoNativeInput.vue';

const props = withDefaults(
	defineProps<ValueInputProps<DateTimeProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const { validationMessages, clearServerViolation } = useFieldServerViolation(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	emit,
	formatDateTimeForDisplay
);

const { fieldMessages, fieldStatus, onUnparseableChange, unparseableInputMessage } =
	useUnparseableInput( validationMessages, clearServerViolation );

const internalInputValue = ref<string>( localInputValueOf( props.modelValue ) );

// The input shows local minutes, so a value read back from it loses its seconds and its offset: while the input shows
// what the value passed in shows, that value stands.
const currentValue = shallowRef<Value | undefined>( props.modelValue );

function localInputValueOf( value: Value | undefined ): string {
	return value?.type === ValueType.String ? toLocalInputValue( ( value as StringValue ).parts[ 0 ] ) : '';
}

watch( () => props.modelValue, ( newValue ) => {
	internalInputValue.value = localInputValueOf( newValue );
	currentValue.value = newValue;
} );

function onInput( newValue: string ): void {
	internalInputValue.value = newValue;
	currentValue.value = newValue !== '' && newValue === localInputValueOf( props.modelValue ) ?
		props.modelValue :
		valueOfLocalInput( newValue );
	emit( 'update:modelValue', currentValue.value );
	clearServerViolation();
}

function valueOfLocalInput( local: string ): Value | undefined {
	const isoValue = fromLocalInputValue( local );
	return isoValue !== undefined ? newStringValue( isoValue ) : undefined;
}

defineExpose<ValueInputExposes>( {
	getCurrentValue: (): Value | undefined => currentValue.value,
	unparseableInputMessage
} );
</script>
