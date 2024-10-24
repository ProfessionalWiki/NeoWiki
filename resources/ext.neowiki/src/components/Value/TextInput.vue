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
import { PropType } from 'vue';
import { CdxField, CdxTextInput, CdxButton, CdxIcon, ValidationStatusType, ValidationMessages } from '@wikimedia/codex';
import { cdxIconTrash, cdxIconAdd } from '@wikimedia/codex-icons';
import { Value } from '@neo/domain/Value';
import { newStringValue } from '@neo/domain/Value';
import { TextProperty } from '@neo/domain/valueFormats/Text.ts';
import { useMultiStringInput, ValidationResult } from '@/composables/useMultiStringInput';

const props = defineProps( {
	// eslint-disable-next-line vue/no-unused-properties
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

const {
	inputValues,
	validationState,
	isAddButtonDisabled,
	isRequiredFieldInValid,
	handleInput,
	handleAdd,
	handleRemove
} = useMultiStringInput( props, emit );

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

	fieldValues.forEach( ( value: string, index: number ) => {
		let messages: ValidationMessages = getErrorMessage( value );
		let status: ValidationStatusType = 'error' in messages ? 'error' : 'success';

		if ( isRequiredFieldInValid.value && index === 0 ) {
			messages = { error: mw.message( 'neowiki-field-required' ).text() };
			status = 'error';
		}

		validation.statuses.push( status );
		validation.messages.push( messages );
		validation.isValid = validation.isValid && status !== 'error';
	} );

	return validation;
};

const onInput = ( value: string, index: number ): void => handleInput( value, index, validateFields );
const addText = (): Promise<void> => handleAdd( 'text' );
const removeText = ( index: number ): void => handleRemove( index, validateFields );

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
