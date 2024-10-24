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
import { type PropType } from 'vue';
import { CdxField, CdxTextInput, CdxButton, CdxIcon } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconLink, cdxIconTrash } from '@wikimedia/codex-icons';
import { type Value } from '@neo/domain/Value';
import { newStringValue } from '@neo/domain/Value';
import { type UrlProperty, isValidUrl } from '@neo/domain/valueFormats/Url.ts';
import { useMultiStringInput, type ValidationResult } from '@/composables/useMultiStringInput';

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
		type: Object as PropType<UrlProperty>,
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

const getErrorMessage = ( isEmpty: boolean ): string => isEmpty ?
	mw.message( 'neowiki-field-required' ).text() :
	mw.message( 'neowiki-field-invalid-url' ).text();

const validateFields = ( valueParts: string[] ): ValidationResult => {
	const validation: ValidationResult = { isValid: true, statuses: [], messages: [] };

	valueParts.forEach( ( valuePart: string, index: number ): void => {
		const url = valuePart.trim();
		const isEmpty: boolean = url === '';
		let fieldIsValid: boolean = isEmpty || isValidUrl( url );

		if ( isEmpty && isRequiredFieldInValid.value && index === 0 ) {
			fieldIsValid = false;
		}

		validation.statuses.push( fieldIsValid ? 'success' : 'error' );
		validation.messages.push( fieldIsValid ? {} : { error: getErrorMessage( isEmpty ) } );
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

		.cdx-text-input {
			flex: 1;
			width: 100%;

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
