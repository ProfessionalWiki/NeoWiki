<template>
	<div class="neo-url-field">
		<b>{{ label }}</b>
		<div
			v-for="( url, index ) in inputValues"
			:key="index"
			class="url-input-wrapper"
		>
			<CdxField
				:status="inputStatuses[index]"
				:messages="validationMessages[index]"
			>
				<CdxTextInput
					:input-ref="`${index}-${property.name.toString()}-url-input`"
					:model-value="url"
					input-type="url"
					:start-icon="cdxIconLink"
					:status="inputStatuses[index]"
					@update:model-value="( value ) => onInput( value, index )"
				/>
			</CdxField>
			<CdxButton
				v-if="index > 0"
				weight="quiet"
				aria-label=""
				class="delete-button"
				@click="removeUrl( index )"
			>
				<CdxIcon :icon="cdxIconTrash" />
			</CdxButton>
		</div>
		<CdxButton
			weight="quiet"
			class="add-url-button"
			aria-label=""
			@click="addUrl"
		>
			<CdxIcon :icon="cdxIconAdd" />
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import { watch, PropType, ref, nextTick } from 'vue';
import { CdxField, CdxTextInput, CdxButton, CdxIcon, ValidationStatusType, ValidationMessages } from '@wikimedia/codex';
import { cdxIconAdd, cdxIconLink, cdxIconTrash } from '@wikimedia/codex-icons';
import type { Value } from '@neo/domain/Value';
import { newStringValue, StringValue, ValueType } from '@neo/domain/Value';
import { UrlProperty } from '@neo/domain/valueFormats/Url.ts';

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

const inputStatuses = ref<ValidationStatusType[]>( [] );
const validationMessages = ref<ValidationMessages[]>( [] );

const inputValues = ref<string[]>( [] );

const isValidUrl = ( url: string ): boolean => {
	try {
		new URL( url );
		return true;
	} catch {
		return false;
	}
};

const validateAndUpdateFields = ( fieldValues: string[] ): boolean => {
	let isValid = true;
	fieldValues.forEach( ( value, index ) => {
		const fieldValid = isValidUrl( value );
		inputStatuses.value[ index ] = value.trim() === '' ? 'default' : ( fieldValid ? 'success' : 'error' );
		validationMessages.value[ index ] = fieldValid ? {} : { error: mw.message( 'neowiki-field-invalid-url' ).text() };
		isValid = isValid && fieldValid;
	} );
	return isValid;
};

const isRequiredValid = ( values: string[] ): boolean => {
	if ( props.property.required && values.every( ( url ) => url.trim() === '' ) ) {
		inputStatuses.value = [ 'error' ];
		validationMessages.value = [ { error: mw.message( 'neowiki-field-required' ).text() } ];
		return false;
	}
	return true;
};

const onInput = ( newValue: string, index: number ): void => {
	const updatedValues = inputValues.value.map( ( value, i ) => i === index ? newValue : value );

	emit( 'update:modelValue', newStringValue( ...updatedValues ) );

	const fieldsValid = isRequiredValid( updatedValues ) === true ? validateAndUpdateFields( updatedValues ) : false;

	emit( 'validation', fieldsValid );
};

const addUrl = async (): Promise<void> => {
	inputValues.value.push( '' );
	const value = newStringValue( ...inputValues.value );
	emit( 'update:modelValue', value );

	await nextTick();
	const inputRef = `${ inputValues.value.length - 1 }-${ props.property.name.toString() }-url-input`;
	focusInput( inputRef );
};

const focusInput = ( inputRef: string ): void => {
	const input = document.querySelector( `[input-ref="${ inputRef }"]` ) as HTMLInputElement | null;
	if ( input ) {
		input.focus();
	}
};
const removeUrl = ( index: number ): void => {
	inputValues.value.splice( index, 1 );
	const value = newStringValue( ...inputValues.value );
	emit( 'update:modelValue', value );
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
	inputStatuses,
	validationMessages,
	onInput,
	addUrl,
	removeUrl
} );
</script>

<style lang="scss">
@import '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss';

.neo-url-field {
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
				color: #54595d;
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
}
</style>
