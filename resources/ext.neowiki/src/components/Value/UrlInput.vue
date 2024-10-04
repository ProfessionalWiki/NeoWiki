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
			:model-value="inputValue"
			input-type="url"
			:start-icon="cdxIconLink"
			@input="onInput"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { computed, ref, watch, PropType } from 'vue';
import { CdxField, CdxTextInput, ValidationStatusType } from '@wikimedia/codex';
import { cdxIconLink } from '@wikimedia/codex-icons';
import { newStringValue, ValueType, StringValue } from '@neo/domain/Value';
import type { Value } from '@neo/domain/Value';

const props = defineProps( {
	modelValue: {
		type: Object as PropType<Value>,
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

const inputValue = computed( () => {
	if ( props.modelValue.type === ValueType.String ) {
		// TODO: support multiple strings in the UI: https://github.com/ProfessionalWiki/NeoExtension/issues/117
		return ( props.modelValue as StringValue ).strings[ 0 ] || '';
	}

	return '';
} );

const onInput = ( event: Event ): void => {
	const value = getStringValueInputEvent( event );

	emit( 'update:modelValue', value );
	updateValidationStatus( validate( value ) );
};

const getStringValueInputEvent = ( event: Event ): StringValue => newStringValue( [ ( event.target as HTMLInputElement ).value ] );

const validate = ( value: StringValue ): ValidationMessages => {
	const messages: ValidationMessages = {};

	if ( props.required && value.strings[ 0 ] === '' ) {
		messages.error = mw.message( 'neowiki-field-required' ).text();
	} else if ( value.strings[ 0 ] !== '' ) {
		try {
			// eslint-disable-next-line no-new
			new URL( value.strings[ 0 ] );
		} catch ( _error ) {
			messages.error = mw.message( 'neowiki-field-invalid-url' ).text();
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
