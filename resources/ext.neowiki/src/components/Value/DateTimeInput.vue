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
import { ref, toRef, watch } from 'vue';
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

const internalInputValue = ref<string>( '' );

function localInputValueOf( value: Value | undefined ): string {
	const str = value?.type === ValueType.String ? ( value as StringValue ).parts[ 0 ] : undefined;
	return str ? toLocalInputValue( str ) : '';
}

const initializeInputValue = ( value: Value | undefined ): void => {
	internalInputValue.value = localInputValueOf( value );
};

initializeInputValue( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	initializeInputValue( newValue );
} );

function onInput( newValue: string ): void {
	internalInputValue.value = newValue;
	const isoValue = fromLocalInputValue( newValue );
	const value = isoValue !== undefined ? newStringValue( isoValue ) : undefined;
	emit( 'update:modelValue', value );
	clearServerViolation();
}

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		// Read back from the input, the same instant comes out in another form, which would count as a change.
		if ( props.modelValue?.type === ValueType.String && internalInputValue.value === localInputValueOf( props.modelValue ) ) {
			return props.modelValue;
		}

		const isoValue = fromLocalInputValue( internalInputValue.value );
		return isoValue !== undefined ? newStringValue( isoValue ) : undefined;
	},
	unparseableInputMessage
} );
</script>
