<template>
	<CdxField
		:status="validationError ? 'error' : 'default'"
		:messages="validationError ? { error: validationError } : {}"
		:required="property.required"
	>
		<template #label>
			{{ label }}
		</template>
		<CdxTextInput
			:model-value="inputValue"
			input-type="number"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { newNumberValue, NumberValue, ValueType } from '@neo/domain/Value';
import { NumberProperty } from '@neo/domain/valueFormats/Number.ts';
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

const inputValue = computed( () => {
	if ( props.modelValue.type === ValueType.Number ) {
		return ( props.modelValue as NumberValue ).number.toString();
	}
	return '';
} );

const valueFormat = NeoWikiServices.getValueFormatRegistry().getFormat( 'number' );

function onInput( newValue: string ): void {
	const value = newValue === '' ? undefined : newNumberValue( Number( newValue ) );
	emit( 'update:modelValue', value );
	validate( value );
}

function validate( value: NumberValue | undefined ): void {
	const errors = valueFormat.validate( value, props.property );
	validationError.value = errors.length === 0 ? null :
		// eslint-disable-next-line es-x/no-nullish-coalescing-operators
		mw.message( `neowiki-field-${ errors[ 0 ].code }`, ...( errors[ 0 ].args ?? [] ) ).text();
}

watch( () => props.property, () => {
	validate( props.modelValue ? props.modelValue as NumberValue : undefined );
} );
</script>
