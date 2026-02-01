<template>
	<CdxField
		:status="validationError === null ? 'default' : 'error'"
		:messages="validationError === null ? {} : { error: validationError }"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ label }}
		</template>
		<template v-if="props.property.description" #description>
			{{ props.property.description }}
		</template>
		<CdxTextInput
			:model-value="internalInputValue"
			:start-icon="startIcon"
			input-type="number"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@/domain/Value';
</script>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { newNumberValue, NumberValue, ValueType } from '@/domain/Value';
import { NumberType, NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = withDefaults(
	defineProps<ValueInputProps<NumberProperty>>(),
	{
		modelValue: () => newNumberValue( NaN ),
		label: ''
	}
);

const startIcon = NeoWikiServices.getComponentRegistry().getIcon( NumberType.typeName );

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

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return isValueEmpty( internalInputValue.value ) ? undefined : newNumberValue( Number( internalInputValue.value ) );
	}
} );

// Initial validation (call after internalInputValue is set)
validate( props.modelValue && props.modelValue.type === ValueType.Number ? props.modelValue as NumberValue : undefined );

</script>
