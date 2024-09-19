<template>
	<CdxField
		:status="validationStatus"
		:messages="validationMessages"
		:required="required"
	>
		<slot name="label" />
		<slot :validate-input="validateInput" />
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField } from '@wikimedia/codex';

const props = defineProps( {
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
	},
	inputType: {
		type: String,
		default: 'text',
		validator: ( value: string ) => {
			const validTypes = [ 'text', 'number', 'url' ];
			// eslint-disable-next-line es-x/no-array-prototype-includes
			return validTypes.includes( value );
		}
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

const emit = defineEmits( [ 'validation' ] );
const validationStatus = ref<'default' | 'error'>( 'default' );

interface ValidationMessages {
	[key: string]: string;
}

const validationMessages = ref<ValidationMessages>( {} );

const validateInput = ( value: string ): void => {
	const messages: { [key: string]: string } = {};

	if ( props.required && !value ) {
		messages.error = mw.message( 'neowiki-field-required' ).text();
	}

	switch ( props.inputType ) {
		case 'text':
			if ( value.length < props.minLength ) {
				messages.error = mw.message( 'neowiki-field-min-length', props.minLength ).text();
			} else if ( value.length > props.maxLength ) {
				messages.error = mw.message( 'neowiki-field-max-length', props.maxLength ).text();
			}
			break;
		case 'number': {
			const numValue = Number( value );
			if ( isNaN( numValue ) ) {
				messages.error = mw.message( 'neowiki-field-invalid-number' ).text();
			} else if ( numValue < props.minValue ) {
				messages.error = mw.message( 'neowiki-field-min-value', props.minValue ).text();
			} else if ( numValue > props.maxValue ) {
				messages.error = mw.message( 'neowiki-field-max-value', props.maxValue ).text();
			}
		}
			break;
		case 'url':
			try {
				const url = new URL( value );
				console.log( url );
			} catch ( error ) {
				console.log( error );
				messages.error = mw.message( 'neowiki-field-invalid-url' ).text();
			}
			break;
	}

	validationMessages.value = messages;
	validationStatus.value = Object.keys( messages ).length > 0 ? 'error' : 'default';
};

watch( validationMessages, ( newMessages ) => {
	emit( 'validation', Object.keys( newMessages ).length === 0 );
} );
</script>
