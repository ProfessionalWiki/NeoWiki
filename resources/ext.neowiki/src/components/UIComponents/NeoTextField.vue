<template>
	<CdxField
		:status="validationStatus"
		:messages="validationMessages"
		:required="required"
		class="neo-text-field"
		:class="{ 'neo-text-field--success': validationStatus === 'success' }"
	>
		<template #label>
			{{ label }}
		</template>
		<CdxTextInput
			v-model="inputValue"
			input-type="text"
			:class="{ 'cdx-text-input--status-success': validationStatus === 'success' }"
			:end-icon="endIcon"
			@input="validateInput"
			@focus="onFocus"
			@blur="onBlur"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';

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
const validationStatus = ref<'default' | 'error' | 'success'>( 'default' );

interface ValidationMessages {
	[key: string]: string;
}

const validationMessages = ref<ValidationMessages>( {} );
const inputValue = ref( props.modelValue );
const hasHadError = ref( false );
const isFocused = ref( false );

const endIcon = computed( () => validationStatus.value === 'success' && hasHadError.value ? cdxIconCheck : undefined );

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

watch( validationMessages, ( newMessages ) => {
	emit( 'validation', Object.keys( newMessages ).length === 0 || validationStatus.value === 'success' );
} );

watch( () => props.modelValue, ( newValue ) => {
	inputValue.value = newValue;
} );
</script>

<style lang="scss" scoped>
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

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
