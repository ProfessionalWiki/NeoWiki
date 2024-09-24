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
			input-type="url"
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
	} else if ( value ) {
		try {
			// eslint-disable-next-line no-new
			new URL( value );
		} catch ( error ) {
			console.log( error );
			messages.error = mw.message( 'neowiki-field-invalid-url' ).text();
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
	inputValue.value = newValue;
} );
</script>
