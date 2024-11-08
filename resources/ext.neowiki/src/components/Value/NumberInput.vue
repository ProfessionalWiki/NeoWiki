<template>
	<CdxField
		:status="validationStatus"
		:messages="validationMessages"
		:required="property.required"
	>
		<template #label>
			{{ label }}
		</template>
		<CdxTextInput
			:model-value="inputValue"
			input-type="number"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxField, CdxTextInput, ValidationStatusType } from '@wikimedia/codex';
import { newNumberValue, NumberValue, ValueType } from '@neo/domain/Value';
import { NumberProperty } from '@neo/domain/valueFormats/Number.ts';
import { ValidationMessages, ValueInputEmits, ValueInputProps } from '@/components/Value/ValueInputContract.ts';

const props = withDefaults(
	defineProps<ValueInputProps<NumberProperty>>(),
	{
		modelValue: () => newNumberValue( NaN ),
		label: ''
	}
);
const emit = defineEmits<ValueInputEmits>();

const validationStatus = ref<ValidationStatusType>( 'default' );
const validationMessages = ref<ValidationMessages>( {} );

const inputValue = computed( () => {
	if ( props.modelValue.type === ValueType.Number ) {
		return ( props.modelValue as NumberValue ).number.toString();
	}
	return '';
} );

const onInput = ( newValue: string ): void => {
	const value = newValue === '' ? undefined : newNumberValue( Number( newValue ) );

	emit( 'update:modelValue', value );
	updateValidationStatus( validate( value ) );
};

const validate = ( value: NumberValue | undefined ): ValidationMessages => {
	const messages: ValidationMessages = {};

	if ( props.property.required && value === undefined ) {
		messages.error = mw.message( 'neowiki-field-required' ).text();
	} else if ( value !== undefined ) {
		if ( props.property.minimum !== undefined && value.number < props.property.minimum ) {
			messages.error = mw.message( 'neowiki-field-min-value', props.property.minimum ).text();
		}
		if ( props.property.maximum !== undefined && value.number > props.property.maximum ) {
			messages.error = mw.message( 'neowiki-field-max-value', props.property.maximum ).text();
		}
	}

	return messages;
};

const updateValidationStatus = ( messages: ValidationMessages ): void => {
	validationMessages.value = messages;
	validationStatus.value = Object.keys( messages ).length > 0 ? 'error' : 'default';

	emit( 'validation', Object.keys( messages ).length === 0 );
};

watch( () => props.property, () => {
	updateValidationStatus( validate( props.modelValue ? props.modelValue as NumberValue : undefined ) );
} );
</script>
