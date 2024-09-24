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
			input-type="text"
			@input="validateInput"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';

const props = defineProps( {
	modelValue: {
		type: String,
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
	minLength: {
		type: Number,
		default: 0
	},
	maxLength: {
		type: Number,
		default: Infinity
	}
} );

const emit = defineEmits( [ 'update:modelValue', 'validation' ] );
const validationStatus = ref<'default' | 'error'>( 'default' );

interface ValidationMessages {
	[key: string]: string;
}

const validationMessages = ref<ValidationMessages>( {} );

const inputValue = ref( props.modelValue );

const validateInput = ( event: Event ): void => {
	const value = ( event.target as HTMLInputElement ).value;
	emit( 'update:modelValue', value );

	const messages: { [key: string]: string } = {};

	if ( props.required && !value ) {
		messages.error = mw.message( 'neowiki-field-required' ).text();
	} else if ( value.length < props.minLength ) {
		messages.error = mw.message( 'neowiki-field-min-length', props.minLength ).text();
	} else if ( value.length > props.maxLength ) {
		messages.error = mw.message( 'neowiki-field-max-length', props.maxLength ).text();
	}

	validationMessages.value = messages;
	validationStatus.value = Object.keys( messages ).length > 0 ? 'error' : 'default';

	emit( 'validation', Object.keys( messages ).length === 0 );
};

watch( validationMessages, ( newMessages ) => {
	emit( 'validation', Object.keys( newMessages ).length === 0 );
} );

watch( () => props.modelValue, ( newValue ) => {
	inputValue.value = newValue;
} );
</script>
