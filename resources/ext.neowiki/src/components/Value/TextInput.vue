<template>
	<CdxField
		:status="validationStatus"
		:messages="validationMessages"
		:required="property.required"
		class="neo-text-field"
		:class="{ 'neo-text-field--success': validationStatus === 'success' }"
	>
		<template #label>
			{{ label }}
		</template>
		<CdxTextInput
			:model-value="inputValue"
			input-type="text"
			:class="{ 'cdx-text-input--status-success': validationStatus === 'success' }"
			:end-icon="endIcon"
			@update:model-value="onInput"
			@focus="onFocus"
			@blur="onBlur"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch, computed, PropType } from 'vue';
import { CdxField, CdxTextInput, ValidationStatusType } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';
import { newStringValue, ValueType, StringValue } from '@neo/domain/Value';
import type { Value } from '@neo/domain/Value';
import { TextProperty } from '@neo/domain/valueFormats/Text.ts';

const props = defineProps( {
	modelValue: {
		type: Object as PropType<Value>,
		default: () => newStringValue( '' )
	},
	label: {
		type: String,
		required: false,
		default: ''
	},
	property: {
		type: Object as PropType<TextProperty>,
		required: true
	}
} );

const emit = defineEmits( [ 'update:modelValue', 'validation' ] );
const validationStatus = ref<ValidationStatusType>( 'default' );

interface ValidationMessages {
	[key: string]: string;
}

const validationMessages = ref<ValidationMessages>( {} );
const hasHadError = ref( false );
const isFocused = ref( false );

const inputValue = computed( () => {
	if ( props.modelValue.type === ValueType.String ) {
		// TODO: support multiple strings in the UI: https://github.com/ProfessionalWiki/NeoExtension/issues/117
		return ( props.modelValue as StringValue ).strings[ 0 ] || '';
	}

	return '';
} );

const endIcon = computed( () => validationStatus.value === 'success' && hasHadError.value ? cdxIconCheck : undefined );

const onInput = ( newValue: string ): void => {
	const value = newStringValue( newValue );

	emit( 'update:modelValue', value );
	updateValidationStatus( validate( value ) );
};

const validate = ( value: StringValue ): ValidationMessages => {
	const messages: ValidationMessages = {};

	if ( props.property.required && value.strings.length === 0 ) {
		messages.error = mw.message( 'neowiki-field-required' ).text();
	} else if ( props.property.minLength !== undefined &&
		value.strings.length > 0 && value.strings[ 0 ].length < props.property.minLength ) { // TODO: adjust for multiple parts
		messages.error = mw.message( 'neowiki-field-min-length', props.property.minLength ).text();
	} else if ( props.property.maxLength !== undefined &&
		value.strings.length > 0 && value.strings[ 0 ].length > props.property.maxLength ) { // TODO: adjust for multiple parts
		messages.error = mw.message( 'neowiki-field-max-length', props.property.maxLength ).text();
	}

	return messages;
};

const updateValidationStatus = ( messages: ValidationMessages ): void => {
	if ( Object.keys( messages ).length > 0 ) {
		validationStatus.value = 'error';
		hasHadError.value = true;
	} else if ( hasHadError.value ) {
		validationStatus.value = 'success';
		messages.success = '';
	} else {
		validationStatus.value = 'default';
	}

	validationMessages.value = messages;
	emit( 'validation', Object.keys( messages ).length === 0 || validationStatus.value === 'success' );
};

const onFocus = (): void => {
	isFocused.value = true;
};

const onBlur = (): void => {
	isFocused.value = false;
};

watch( validationMessages, ( newMessages ) => { // TODO: this can probably be removed
	emit( 'validation', Object.keys( newMessages ).length === 0 || validationStatus.value === 'success' );
} );
</script>

<style lang="scss" scoped>
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

@keyframes success-pulse {
	0% {
		transform: scale( 1 );
	}

	50% {
		transform: scale( 1.01 );
	}

	100% {
		transform: scale( 1 );
	}
}

.neo-text-field {
	&--success {
		animation: success-pulse 0.5s ease-in-out;
	}

	:deep( .cdx-text-input__input ) {
		transition: border-color 0.3s ease, box-shadow 0.3s ease;
	}

	:deep( .cdx-text-input--status-success .cdx-text-input__input ) {
		border-color: #14866d !important;
		box-shadow: inset 0 0 0 1px #14866d;
	}

	:deep( .cdx-field__help-text--status-success ) {
		color: #14866d;
	}

	:deep( .cdx-field__control ) {
		.cdx-text-input--with-focus-effect {
			position: relative;

			&::after {
				content: '';
				position: absolute;
				bottom: 0;
				left: 0;
				width: 0;
				height: 1px;
				background-color: rgba( 0, 69, 220 );
				transition: width 0.3s ease;
				border-radius: 5px;
			}

			&:focus-within::after {
				width: 100%;
			}
		}
	}
}

:deep( .cdx-text-input__input:focus ) {
	border-color: #36c !important;
	box-shadow: inset 0 0 0 1px #36c;
}

</style>
