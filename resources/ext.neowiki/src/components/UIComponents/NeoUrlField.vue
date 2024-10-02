<template>
	<CdxField
		:status="validationStatus"
		:messages="validationMessages"
		:required="required"
		class="neo-url-field neo-text-field"
	>
		<template #label>
			{{ label }}
		</template>
		<CdxTextInput
			v-model="inputValue"
			input-type="url"
			:start-icon="cdxIconLink"
			@input="validateInput"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField, CdxTextInput, ValidationStatusType } from '@wikimedia/codex';
import { cdxIconLink } from '@wikimedia/codex-icons';

const props = defineProps( {
	modelValue: {
		type: String,
		required: true
	},
	label: {
		type: String,
		required: false,
		default: ''
	},
	required: {
		type: Boolean,
		default: false
	}
} );

const emit = defineEmits( [ 'update:modelValue', 'validation' ] );
const validationStatus = ref<ValidationStatusType>( 'default' );

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

<style lang="scss" scoped>
.neo-url-field {
	.cdx-text-input {
		&__input {
			padding-left: 36px; // Make room for the icon
		}

		&__start-icon {
			left: 8px;
			color: #54595d;
		}
	}
}
</style>
