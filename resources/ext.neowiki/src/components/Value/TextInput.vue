<template>
	<div class="neo-text-field">
		<label>{{ label }}</label>
		<div
			v-for="( text, index ) in inputValues"
			:key="index"
			class="text-input-wrapper"
		>
			<CdxField
				:status="validationState.statuses[index]"
				:messages="validationState.messages[index]"
			>
				<CdxTextInput
					:model-value="text"
					:input-ref="`${index}-${property.name.toString()}-text-input`"
					input-type="text"
					:status="validationState.statuses[index]"
					@update:model-value="value => onInput( value, index )"
				/>
			</CdxField>
			<CdxButton
				v-if="index > 0"
				weight="quiet"
				aria-hidden="false"
				class="delete-button"
				@click="removeText( index )"
			>
				<CdxIcon :icon="cdxIconTrash" />
			</CdxButton>
		</div>
		<CdxButton
			weight="quiet"
			aria-hidden="false"
			class="add-text-button"
			:class="{ 'add-btn-disabled': isAddButtonDisabled }"
			:disabled="isAddButtonDisabled"
			@click="addText"
		>
			<CdxIcon :icon="cdxIconAdd" />
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import { ref, watch, computed, PropType, nextTick } from 'vue';
import { CdxField, CdxTextInput, CdxButton, CdxIcon, ValidationStatusType, ValidationMessages } from '@wikimedia/codex';
import { cdxIconTrash, cdxIconAdd } from '@wikimedia/codex-icons';
import { newStringValue, ValueType, StringValue } from '@neo/domain/Value';
import type { Value } from '@neo/domain/Value';
import { TextProperty } from '@neo/domain/valueFormats/Text.ts';

type ValidationResult = {
	isValid: boolean;
	statuses: ValidationStatusType[];
	messages: ValidationMessages[];
};

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

const inputValues = ref<string[]>( [ '' ] );

const validationState = ref<ValidationResult>( {
	isValid: true,
	statuses: [] as ValidationStatusType[],
	messages: [] as ValidationMessages[]
} );

const isAddButtonDisabled = computed( (): boolean => inputValues.value.some( ( value: string ) => value.trim() === '' || !validationState.value.isValid )
);

const getErrorMessage = ( value: string ): ValidationMessages => {
	const isEmpty: boolean = value.trim() === '';
	const errors = [
		{
			condition: !isEmpty && props.property.minLength && value.trim().length < props.property.minLength,
			message: mw.message( 'neowiki-field-min-length', props.property.minLength ).text()
		},
		{
			condition: props.property.maxLength && value.trim().length > props.property.maxLength,
			message: mw.message( 'neowiki-field-max-length', props.property.maxLength ).text()
		}
	];

	const error = errors.find( ( check ) => check.condition );

	return error !== undefined ? { error: error.message } : {};
};

const validateFields = ( fieldValues: string[] ): ValidationResult => {
	const validation: ValidationResult = { isValid: true, statuses: [], messages: [] };
	const areAllFieldsEmpty = fieldValues.every( ( value ) => value.trim() === '' );
	const isRequiredFieldValid = areAllFieldsEmpty && props.property.required;

	fieldValues.forEach( ( value: string, index: number ) => {
		let messages: ValidationMessages = getErrorMessage( value );
		let status: ValidationStatusType = 'error' in messages ? 'error' : 'success';

		if ( isRequiredFieldValid && index === 0 ) {
			messages = { error: mw.message( 'neowiki-field-required' ).text() };
			status = 'error';
		}

		validation.statuses.push( status );
		validation.messages.push( messages );
		validation.isValid = validation.isValid && status !== 'error';
	} );

	return validation;
};
const onInput = ( newValue: string, index: number ): void => {
	inputValues.value[ index ] = newValue;

	const validation = validateFields( inputValues.value );
	validationState.value = validation;

	emit( 'update:modelValue', newStringValue( ...inputValues.value ) );
	emit( 'validation', validation.isValid );
};

const addText = async (): Promise<void> => {
	inputValues.value.push( '' );

	emit( 'update:modelValue', newStringValue( ...inputValues.value ) );
	emit( 'validation', false );
	await nextTick();

	const inputRef = `${ inputValues.value.length - 1 }-${ props.property.name }-text-input`;
	focusInput( inputRef );
};

const removeText = ( index: number ): void => {
	inputValues.value.splice( index, 1 );

	const validation = validateFields( inputValues.value );
	validationState.value = validation;

	emit( 'update:modelValue', newStringValue( ...inputValues.value ) );
	emit( 'validation', validation.isValid );
};

const focusInput = ( inputRef: string ): void => {
	const input = document.querySelector( `[input-ref="${ inputRef }"]` ) as HTMLInputElement | null;
	input?.focus();
};

watch( () => props.modelValue, ( newValue ) => {
	if ( newValue.type === ValueType.String ) {
		inputValues.value = ( newValue as StringValue ).strings;
	} else {
		inputValues.value = [ '' ];
	}
}, { immediate: true, deep: true } );

defineExpose( {
	inputValues,
	validationState,
	onInput,
	addText,
	removeText
} );
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.neo-text-field {
	label {
		font-weight: bold;
	}

	.text-input-wrapper {
		display: flex;
		align-items: center;
		margin-bottom: 8px;

		.cdx-text-input {
			flex: 1;
		}

		.delete-button {
			margin-left: 8px;

			.cdx-icon {
				color: $color-destructive;
			}
		}
	}

	.add-text-button {
		float: right;
		margin-top: 8px;

		.cdx-icon {
			color: $color-success;
		}
	}

	.add-btn-disabled {
		.cdx-icon {
			opacity: 0.35;
			cursor: $cursor-base--disabled;
		}
	}
}
</style>
