<template>
	<div :class="rootClass">
		<label>{{ label }}</label>
		<div
			v-for="( value, index ) in inputValues"
			:key="index"
			:class="`${rootClass}__input-wrapper`"
		>
			<CdxField
				:status="errors[index] === null ? 'success' : 'error'"
				:messages="errors[index] === null ? {} : { error: errors[index] }"
			>
				<CdxTextInput
					:model-value="value"
					:input-ref="`${index}-${property.name.toString()}-${inputType}-input`"
					:input-type="inputType"
					:start-icon="startIcon"
					@update:model-value="value => onInput( value, index )"
				/>
			</CdxField>
			<CdxButton
				v-if="index > 0"
				weight="quiet"
				aria-hidden="false"
				class="delete-button"
				@click="() => removeValue( index )"
			>
				<CdxIcon :icon="cdxIconTrash" />
			</CdxButton>
		</div>
		<CdxButton
			v-if="property.multiple"
			weight="quiet"
			aria-hidden="false"
			:class="[
				`${rootClass}__add-button`,
				{ 'add-btn-disabled': hasInvalidField }
			]"
			:disabled="hasInvalidField"
			@click="addValue"
		>
			<CdxIcon :icon="cdxIconAdd" />
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import { computed, nextTick, ref, watch } from 'vue';
import { CdxField, CdxTextInput, CdxButton, CdxIcon, TextInputType } from '@wikimedia/codex';
import { cdxIconTrash, cdxIconAdd } from '@wikimedia/codex-icons';
import { newStringValue, StringValue, Value, ValueType } from '@neo/domain/Value';
import { ValueInputEmits, ValueInputProps } from '@/components/Value/ValueInputContract';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { MultiStringProperty } from '@neo/domain/PropertyDefinition';
import type { Icon } from '@wikimedia/codex-icons';

const props = withDefaults(
	defineProps<ValueInputProps<MultiStringProperty> & {
		propertyTypeName: string;
		inputType: TextInputType;
		rootClass: string;
		startIcon?: Icon;
	}>(),
	{
		modelValue: () => newStringValue( '' ),
		label: '',
		startIcon: undefined
	}
);

const emit = defineEmits<ValueInputEmits>();

const buildInitialInputValues = ( value: Value ): string[] => {
	if ( value.type === ValueType.String ) {
		const strings = ( value as StringValue ).strings;
		return strings.length > 0 ? strings : [ '' ];
	}
	return [ '' ];
};

const inputValues = ref<string[]>( buildInitialInputValues( props.modelValue ) );
const errors = ref<( string | null )[]>( inputValues.value.map( () => null ) );

const hasInvalidField = computed( () =>
	inputValues.value.some( ( value ) => value.trim() === '' ) || errors.value.some( ( error ) => error !== null )
);

const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( props.propertyTypeName );

function validate(): void {
	// First validate the whole array
	const allValues = newStringValue( ...inputValues.value );
	const allErrors = propertyType.validate( allValues, props.property );
	const hasValidValue = allErrors.length === 0;

	// Reset all errors
	errors.value = inputValues.value.map( () => null );

	// If we have uniqueness errors, show them on the duplicates
	if ( allErrors.some( ( error ) => error.code === 'unique' ) ) {
		const seen = new Set<string>();
		inputValues.value.forEach( ( text, index ) => {
			const trimmed = text.trim();
			if ( trimmed !== '' && seen.has( trimmed ) ) {
				errors.value[ index ] = mw.message( 'neowiki-field-unique' ).text();
			}
			seen.add( trimmed );
		} );
		return;
	}

	// Then validate individual fields, but ignore empty fields if we have valid values
	inputValues.value.forEach( ( text, index ) => {
		if ( hasValidValue && text.trim() === '' ) {
			return;
		}

		const value = newStringValue( text );
		const validationErrors = propertyType.validate( value, props.property );

		if ( validationErrors.length > 0 ) {
			errors.value[ index ] = mw.message(
				`neowiki-field-${ validationErrors[ 0 ].code }`,
				...( validationErrors[ 0 ].args ?? [] )
			).text();
		}
	} );
}

function onInput( value: string, index: number ): void {
	inputValues.value[ index ] = value;
	const newValue = newStringValue( ...inputValues.value );
	emit( 'update:modelValue', newValue.strings.length > 0 ? newValue : undefined );
	validate();
}

function addValue(): void {
	inputValues.value.push( '' );
	const newValue = newStringValue( ...inputValues.value );
	emit( 'update:modelValue', newValue.strings.length > 0 ? newValue : undefined );
	validate();

	nextTick( () => {
		focusLastInput();
	} );
}

function focusLastInput(): void {
	focusInput( `${ inputValues.value.length - 1 }-${ props.property.name }-${ props.inputType }-input` );
}

function focusInput( inputRef: string ): void {
	const input = document.querySelector( `[input-ref="${ inputRef }"]` ) as HTMLInputElement | null;
	input?.focus();
}

function removeValue( index: number ): void {
	inputValues.value.splice( index, 1 );
	const newValue = newStringValue( ...inputValues.value );
	emit( 'update:modelValue', newValue.strings.length > 0 ? newValue : undefined );
	validate();
}

watch( () => props.property, validate );
</script>

<style lang="scss">
@use '@wikimedia/codex-design-tokens/theme-wikimedia-ui.scss' as *;

button.delete-button {
	margin-left: 8px;

	.cdx-icon {
		color: $color-destructive !important;
	}
}

.add-btn-disabled {
	.cdx-icon {
		opacity: 0.35;
		cursor: $cursor-base--disabled;
	}
}
</style>
