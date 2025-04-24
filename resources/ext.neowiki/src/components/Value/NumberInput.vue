<template>
	<CdxField
		:status="validationError === null ? 'default' : 'error'"
		:messages="validationError === null ? {} : { error: validationError }"
		:required="property.required"
	>
		<template #label>
			{{ label }}
		</template>
		<CdxTextInput
			:model-value="internalInputValue"
			input-type="number"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@neo/domain/Value';

export interface NumberInputExposed {
	getCurrentValue(): Value | undefined;
}
</script>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { newNumberValue, NumberValue, ValueType } from '@neo/domain/Value';
import { NumberType, NumberProperty } from '@neo/domain/propertyTypes/Number.ts';
import { ValueInputEmits, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = withDefaults(
	defineProps<ValueInputProps<NumberProperty>>(),
	{
		modelValue: () => newNumberValue( NaN ),
		label: ''
	}
);
const emit = defineEmits<ValueInputEmits>();

const validationError = ref<string | null>( null );

const internalInputValue = ref<string>( '' );

const initializeInputValue = ( value: Value | undefined ): void => {
	if ( value && value.type === ValueType.Number ) {
		const num = ( value as NumberValue ).number;
		internalInputValue.value = isNaN( num ) ? '' : num.toString();
	} else {
		internalInputValue.value = '';
	}
};

initializeInputValue( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	initializeInputValue( newValue );
	validate( newValue && newValue.type === ValueType.Number ? newValue as NumberValue : undefined );
} );

const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( NumberType.typeName );

function onInput( newValue: string ): void {
	internalInputValue.value = newValue; // Update local state
	const value = newValue === '' ? undefined : newNumberValue( Number( newValue ) );
	emit( 'update:modelValue', value ); // Emit for potential v-model usage
	validate( value );
}

function validate( value: NumberValue | undefined ): void {
	const errors = propertyType.validate( value, props.property );
	validationError.value = errors.length === 0 ? null :
		mw.message( `neowiki-field-${ errors[ 0 ].code }`, ...( errors[ 0 ].args ?? [] ) ).text();
}

watch( () => props.property, () => {
	validate( props.modelValue && props.modelValue.type === ValueType.Number ? props.modelValue as NumberValue : undefined );
} );

const isValueEmpty = ( inputString: string ): boolean =>
	inputString === '' || isNaN( Number( inputString ) );

const getCurrentValue = (): Value | undefined =>
	!isValueEmpty( internalInputValue.value ) ? newNumberValue( Number( internalInputValue.value ) ) : undefined;

defineExpose<NumberInputExposed>( {
	getCurrentValue
} );

// Initial validation (call after internalInputValue is set)
validate( props.modelValue && props.modelValue.type === ValueType.Number ? props.modelValue as NumberValue : undefined );

</script>
