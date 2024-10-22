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
import { watch, PropType, ref, nextTick, computed } from 'vue';
import { CdxField, CdxTextInput, CdxButton, CdxIcon, ValidationStatusType, ValidationMessages } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconLink, cdxIconTrash } from '@wikimedia/codex-icons';
import type { Value } from '@neo/domain/Value';
import { newStringValue, StringValue, ValueType } from '@neo/domain/Value';
import { UrlProperty } from '@neo/domain/valueFormats/Url.ts';

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
		type: Object as PropType<UrlProperty>,
		required: true
	}
} );

const emit = defineEmits( [ 'update:modelValue', 'validation' ] );

const inputValues = ref<string[]>( [] );

const isAddButtonDisabled = computed( (): boolean => inputValues.value.some( ( value: string ) => value.trim() === '' || !validationState.value.isValid ) );

const validationState = ref<ValidationResult>( {
	isValid: true,
	statuses: [] as ValidationStatusType[],
	messages: [] as ValidationMessages[]
} );

const isValidUrl = ( url: string ): boolean => {
	try {
		new URL( url );
		return true;
	} catch {
		return false;
	}
};

const getErrorMessage = ( isEmpty: boolean ): string => isEmpty ?
	mw.message( 'neowiki-field-required' ).text() :
	mw.message( 'neowiki-field-invalid-url' ).text();

const validateFields = ( valueParts: string[] ): ValidationResult => {
	const validation: ValidationResult = { isValid: true, statuses: [], messages: [] };
	const isSingleFieldRequired: boolean = valueParts.length === 1 && props.property.required;

	valueParts.forEach( ( valuePart: string ): void => {
		const url = valuePart.trim();
		const isEmpty: boolean = url === '';
		let fieldIsValid: boolean = isEmpty || isValidUrl( url );

		if ( isEmpty && isSingleFieldRequired ) {
			fieldIsValid = false;
		}

		validation.statuses.push( fieldIsValid ? 'success' : 'error' );
		validation.messages.push( fieldIsValid ? {} : { error: getErrorMessage( isEmpty ) } );
		validation.isValid = validation.isValid && fieldIsValid;
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

const addUrl = async (): Promise<void> => {
	inputValues.value.push( '' );

	emit( 'update:modelValue', newStringValue( ...inputValues.value ) );
	emit( 'validation', false );
	await nextTick();

	const inputRef = `${ inputValues.value.length - 1 }-${ props.property.name }-url-input`;
	focusInput( inputRef );
};

const removeUrl = ( index: number ): void => {
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
		const parts = ( newValue as StringValue ).strings;
		inputValues.value = parts.length === 0 ? [ '' ] : parts;
	} else {
		inputValues.value = [ '' ];
	}
}, { immediate: true, deep: true } );

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
