<template>
	<div class="neo-url-field">
		<label>{{ label }}</label>
		<div
			v-for="( url, index ) in inputValues"
			:key="index"
			class="url-input-wrapper"
		>
			<CdxField
				:status="validationState.statuses[index]"
				:messages="validationState.messages[index]"
			>
				<CdxTextInput
					:input-ref="`${index}-${property.name.toString()}-url-input`"
					:model-value="url"
					input-type="url"
					:start-icon="cdxIconLink"
					:status="validationState.statuses[index]"
					@update:model-value="value => onInput( value, index )"
				/>
			</CdxField>
			<CdxButton
				v-if="index > 0"
				weight="quiet"
				aria-hidden="false"
				class="delete-button"
				@click="removeUrl( index )"
			>
				<CdxIcon :icon="cdxIconTrash" />
			</CdxButton>
		</div>
		<CdxButton
			v-if="property.multiple"
			weight="quiet"
			aria-hidden="false"
			class="add-url-button"
			:class="{ 'add-btn-disabled': isAddButtonDisabled }"
			:disabled="isAddButtonDisabled"
			@click="addUrl"
		>
			<CdxIcon :icon="cdxIconAdd" />
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import { CdxField, CdxTextInput, CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconLink, cdxIconTrash } from '@wikimedia/codex-icons';
import { newStringValue } from '@neo/domain/Value';
import { UrlProperty, isValidUrl } from '@neo/domain/valueFormats/Url.ts';
import { useMultiStringInput } from '@/composables/useMultiStringInput';
import { ValueInputEmits, ValueInputProps, ValidationState } from '@/components/Value/ValueInputContract';

const props = withDefaults(
	defineProps<ValueInputProps<UrlProperty>>(),
	{
		modelValue: () => newStringValue( '' ),
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const {
	inputValues,
	validationState,
	isAddButtonDisabled,
	isRequiredFieldInValid,
	uniquenessRequirementIsMet,
	handleInput,
	handleAdd,
	handleRemove
} = useMultiStringInput( props, emit );

const getErrorMessage = ( isEmpty: boolean ): string => isEmpty ?
	mw.message( 'neowiki-field-required' ).text() :
	mw.message( 'neowiki-field-invalid-url' ).text();

const validateFields = ( valueParts: string[] ): ValidationState => {
	const validation: ValidationState = { isValid: true, statuses: [], messages: [] };

	valueParts.forEach( ( valuePart: string, index: number ): void => {
		const url = valuePart.trim();
		const isEmpty: boolean = url === '';
		let fieldIsValid: boolean = isEmpty || isValidUrl( url );
		let errorMessage = getErrorMessage( isEmpty );

		if ( isEmpty && isRequiredFieldInValid.value && index === 0 ) {
			fieldIsValid = false;
		} else {
			// TODO: error should be shown on the field that caused error
			if ( !uniquenessRequirementIsMet() && index === valueParts.length - 1 ) {
				errorMessage = mw.message( 'neowiki-field-unique' ).text();
				fieldIsValid = false;
			}
		}

		validation.statuses.push( fieldIsValid ? 'success' : 'error' );
		validation.messages.push( fieldIsValid ? {} : { error: errorMessage } );
		validation.isValid = validation.isValid && fieldIsValid;
	} );

	return validation;
};

const onInput = ( value: string, index: number ): void => handleInput( value, index, validateFields );
const addUrl = (): Promise<void> => handleAdd( 'url' );
const removeUrl = ( index: number ): void => handleRemove( index, validateFields );

defineExpose( {
	inputValues,
	validationState,
	onInput,
	addUrl,
	removeUrl
} );
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

.neo-url-field {
	label {
		font-weight: bold;
	}

	.url-input-wrapper {
		display: flex;
		align-items: center;
		margin-bottom: 8px;

		.cdx-field {
			flex: 0 0 100%;
		}

		.cdx-text-input {
			&__input {
				padding-left: 36px;
			}

			&__start-icon {
				left: 8px;
				color: $color-subtle;
			}
		}

		.delete-button {
			margin-left: 8px;
			padding: 4px;

			.cdx-icon {
				color: $color-destructive;
			}
		}
	}

	.add-url-button {
		margin-top: 8px;
		float: right;

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
