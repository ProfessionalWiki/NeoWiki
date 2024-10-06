<template>
	<CdxField
		:status="validationStatus"
		:messages="validationMessages"
		:required="required"
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
import { ref, watch, computed, PropType } from 'vue';
import { CdxField, CdxTextInput, ValidationStatusType } from '@wikimedia/codex';
import { newNumberValue, ValueType, NumberValue } from '@neo/domain/Value';
import type { Value } from '@neo/domain/Value';

const props = defineProps( {
	modelValue: {
		type: Object as PropType<Value>,
		default: () => newNumberValue( NaN )
	},
	label: {
		type: String,
		required: false,
		default: ''
	},
	required: {
		type: Boolean,
		default: false
	},
	minValue: {
		type: Number,
		default: -Infinity
	},
	maxValue: {
		type: Number,
		default: Infinity
	}
} );

const emit = defineEmits( [ 'update:modelValue', 'validation' ] );
const validationStatus = ref<ValidationStatusType>( 'default' );

interface ValidationMessages {
	[key: string]: string;
}

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

	if ( props.required && value === undefined ) {
		messages.error = mw.message( 'neowiki-field-required' ).text();
	} else if ( value !== undefined ) {
		if ( value.number < props.minValue ) {
			messages.error = mw.message( 'neowiki-field-min-value', props.minValue ).text();
		} else if ( value.number > props.maxValue ) {
			messages.error = mw.message( 'neowiki-field-max-value', props.maxValue ).text();
		}
	}

	return messages;
};

const updateValidationStatus = ( messages: ValidationMessages ): void => {
	validationMessages.value = messages;
	validationStatus.value = Object.keys( messages ).length > 0 ? 'error' : 'default';

	emit( 'validation', Object.keys( messages ).length === 0 );
};

watch( validationMessages, ( newMessages ) => { // TODO: this can probably be removed
	emit( 'validation', Object.keys( newMessages ).length === 0 );
} );
</script>
