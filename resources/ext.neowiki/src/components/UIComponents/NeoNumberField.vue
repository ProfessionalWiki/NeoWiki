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
			v-model="inputValue"
			input-type="number"
			@input="validateInput"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField, CdxTextInput, ValidationStatusType } from '@wikimedia/codex';

const props = defineProps( {
	modelValue: {
		type: Number,
		required: true
	},
	label: {
		type: String,
		required: true
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

const inputValue = ref( props.modelValue.toString() );

const validateInput = ( event: Event ): void => {
	const value = ( event.target as HTMLInputElement ).value;
	const numValue = value === '' ? null : Number( value );
	emit( 'update:modelValue', numValue );

	const messages: { [key: string]: string } = {};

	if ( props.required && ( value === '' || numValue === null ) ) {
		messages.error = mw.message( 'neowiki-field-required' ).text();
	} else if ( numValue !== null ) {
		if ( numValue < props.minValue ) {
			messages.error = mw.message( 'neowiki-field-min-value', props.minValue ).text();
		} else if ( numValue > props.maxValue ) {
			messages.error = mw.message( 'neowiki-field-max-value', props.maxValue ).text();
		}
	}

	validationMessages.value = messages;
	validationStatus.value = Object.keys( messages ).length > 0 ? 'error' : 'default';

	emit( 'validation', Object.keys( messages ).length === 0 );
};

watch( validationMessages, ( newMessages ) => {
	emit( 'validation', Object.keys( newMessages ).length === 0 );
} );

watch( () => props.modelValue, ( newValue ) => {
	inputValue.value = newValue !== null && newValue !== undefined ? newValue.toString() : '';
} );
</script>
