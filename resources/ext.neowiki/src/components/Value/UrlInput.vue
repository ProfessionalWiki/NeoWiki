<template>
	<CdxField
		:is-fieldset="true"
		:status="fieldMessages.error && !props.property.multiple ? 'error' : 'default'"
		:messages="fieldMessages"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ props.label }}
		</template>
		<NeoMultiTextInput
			v-if="props.property.multiple"
			:model-value="displayValues"
			:label="props.label"
			:messages="inputMessages"
			:start-icon="startIcon"
			@update:model-value="onInput"
		/>
		<CdxTextInput
			v-else
			:model-value="displayValues[ 0 ]"
			:start-icon="startIcon"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@neo/domain/Value.ts';
</script>

<script setup lang="ts">
import { ref, watch, computed } from 'vue';
import { CdxField, CdxTextInput, ValidationMessages } from '@wikimedia/codex';
import NeoMultiTextInput from '@/components/common/NeoMultiTextInput.vue';
import { UrlProperty, UrlType } from '@neo/domain/propertyTypes/Url.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract';
import { newStringValue, ValueType, StringValue } from '@neo/domain/Value.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { validateValue } from '@/composables/useValueValidation.ts';

const props = withDefaults(
	defineProps<ValueInputProps<UrlProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const startIcon = computed( () => NeoWikiServices.getComponentRegistry().getIcon( UrlType.typeName ) );

const emit = defineEmits<ValueInputEmits>();

const internalValue = ref<StringValue | undefined>( undefined );
const fieldMessages = ref<ValidationMessages>( {} );
const inputMessages = ref<ValidationMessages[]>( [] );

function initializeInternalValue( value: Value | undefined ): void {
	if ( value && value.type === ValueType.String ) {
		const stringVal = value as StringValue;
		internalValue.value = stringVal.strings.length > 0 && stringVal.strings.some( ( s ) => s !== '' ) ?
			stringVal :
			undefined;
	} else {
		if ( value !== undefined ) {
			// console.error( 'UrlInput received non-String value:', value );
		}
		internalValue.value = undefined;
	}
}

initializeInternalValue( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	initializeInternalValue( newValue );
	const { errors } = validate( displayValues.value );
	inputMessages.value = errors;
	fieldMessages.value = getFieldMessages( errors );
} );

const displayValues = computed( (): string[] =>
	internalValue.value ? internalValue.value.strings : []
);

const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( UrlType.typeName );

function validate( inputValues: string[] ): { errors: ValidationMessages[]; validStrings: string[] } {
	const perInputErrors: ValidationMessages[] = Array( inputValues.length ).fill( {} );
	const validStrings: string[] = [];

	inputValues.forEach( ( inputValue, index ) => {
		if ( props.property.uniqueItems && validStrings.includes( inputValue ) ) {
			perInputErrors[ index ] = { error: 'neowiki-field-unique' };
		} else {
			const value = newStringValue( inputValue );
			const validationErrors = validateValue( value, propertyType, props.property );
			perInputErrors[ index ] = validationErrors;
			validStrings.push( inputValue );
		}
	} );

	return {
		errors: perInputErrors,
		validStrings: validStrings.filter( ( s, index ) => s !== '' && !perInputErrors[ index ]?.error )
	};
}

function onInput( newValue: string | string[] ): void {
	const inputValues = typeof newValue === 'string' ? [ newValue ] : newValue;
	const { errors, validStrings } = validate( inputValues );

	inputMessages.value = errors;
	fieldMessages.value = getFieldMessages( errors );

	let newStringValueInstance: StringValue | undefined;
	if ( validStrings.length > 0 ) {
		newStringValueInstance = newStringValue( ...validStrings );
	} else {
		newStringValueInstance = undefined;
	}

	// Only update the internal value if it has changed
	if ( JSON.stringify( internalValue.value ) !== JSON.stringify( newStringValueInstance ) ) {
		internalValue.value = newStringValueInstance;
	}

	emit( 'update:modelValue', newStringValueInstance );
}

function getFieldMessages( errors: ValidationMessages[] ): ValidationMessages {
	if ( !props.property.multiple ) {
		// Return the first error if there is one
		return errors.some( ( error ) => error.error ) ?
			{ error: errors[ 0 ].error } :
			{};
	}

	// TODO: Return an overall error message if needed
	return {};
}

watch( () => props.property, () => {
	const { errors } = validate( displayValues.value );
	inputMessages.value = errors;
	fieldMessages.value = getFieldMessages( errors );
}, { deep: true } );

const initialValidationResult = validate( displayValues.value );
inputMessages.value = initialValidationResult.errors;
fieldMessages.value = getFieldMessages( initialValidationResult.errors );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return internalValue.value;
	}
} );
</script>
