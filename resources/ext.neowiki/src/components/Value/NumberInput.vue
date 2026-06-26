<template>
	<CdxField
		:status="validationError === null ? 'default' : 'error'"
		:messages="validationError === null ? {} : { error: validationError }"
		:optional="props.property.required === false"
	>
		<template #label>
			{{ label }}
			<CdxIcon
				v-if="props.property.description"
				v-tooltip="props.property.description"
				:icon="cdxIconInfo"
				class="ext-neowiki-value-input__description-icon"
				size="small"
			/>
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
import { ref, toRef, watch } from 'vue';
import { CdxField, CdxIcon, CdxTextInput } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { newNumberValue, NumberValue, ValueType } from '@/domain/Value';
import { NumberType, NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useFieldServerViolation } from '@/composables/useFieldServerViolation.ts';
import { liveValidationErrors } from '@/composables/useValueValidation.ts';

const props = withDefaults(
	defineProps<ValueInputProps<NumberProperty>>(),
	{
		modelValue: () => newNumberValue( NaN ),
		label: ''
	}
);

const startIcon = NeoWikiServices.getComponentRegistry().getIcon( NumberType.typeName );

const emit = defineEmits<ValueInputEmits>();

const liveValidationError = ref<string | null>( null );

const { validationError, clearServerViolation } = useFieldServerViolation(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	liveValidationError,
	emit
);

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
	internalInputValue.value = newValue;
	const value = newValue === '' ? undefined : newNumberValue( Number( newValue ) );
	emit( 'update:modelValue', value );
	validate( value );
	clearServerViolation();
}

function validate( value: NumberValue | undefined ): void {
	const errors = liveValidationErrors( value, propertyType, props.property );
	liveValidationError.value = errors.length === 0 ? null :
		mw.message( `neowiki-field-${ errors[ 0 ].code }`, ...( errors[ 0 ].args ?? [] ) ).text();
}

watch( () => props.property, () => {
	validate( props.modelValue && props.modelValue.type === ValueType.Number ? props.modelValue as NumberValue : undefined );
} );

const isInputEmpty = ( inputString: string ): boolean =>
	inputString === '' || isNaN( Number( inputString ) );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return isInputEmpty( internalInputValue.value ) ? undefined : newNumberValue( Number( internalInputValue.value ) );
	}
} );

// Initial validation (call after internalInputValue is set)
validate( props.modelValue && props.modelValue.type === ValueType.Number ? props.modelValue as NumberValue : undefined );

</script>
