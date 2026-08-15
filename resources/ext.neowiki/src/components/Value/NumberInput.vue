<template>
	<CdxField
		:status="fieldStatus"
		:messages="fieldMessages"
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
			@input="onNativeInput"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@/domain/Value';
</script>

<script setup lang="ts">
import { computed, ref, toRef, watch } from 'vue';
import { CdxField, CdxIcon, CdxTextInput, ValidationMessages } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { newNumberValue, NumberValue, ValueType } from '@/domain/Value';
import { NumberType, NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';
import { useFieldServerViolation } from '@/composables/useFieldServerViolation.ts';

const props = withDefaults(
	defineProps<ValueInputProps<NumberProperty>>(),
	{
		modelValue: () => newNumberValue( NaN ),
		label: ''
	}
);

const startIcon = NeoWikiServices.getComponentRegistry().getIcon( NumberType.typeName );

const emit = defineEmits<ValueInputEmits>();

const { validationMessages, validationStatus, clearServerViolation } = useFieldServerViolation(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	emit
);

const internalInputValue = ref<string>( '' );

// A number input shows text it cannot parse, such as "5foo", while reporting an
// empty value to JavaScript; the raw characters are never exposed. badInput is
// the only signal that the field is not the empty field it claims to be.
const unparseableInput = ref( false );

const initializeInputValue = ( value: Value | undefined ): void => {
	const number = value && value.type === ValueType.Number ? ( value as NumberValue ).number : NaN;
	const text = isNaN( number ) ? '' : number.toString();

	// An incoming value that renders as the text already bound leaves the widget's
	// DOM untouched, so text it cannot parse is still on screen: keep flagging it.
	if ( text === internalInputValue.value ) {
		return;
	}

	internalInputValue.value = text;
	// The incoming value replaces whatever the user had typed.
	unparseableInput.value = false;
};

initializeInputValue( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	initializeInputValue( newValue );
} );

function onInput( newValue: string ): void {
	internalInputValue.value = newValue;
	const value = newValue === '' ? undefined : newNumberValue( Number( newValue ) );
	emit( 'update:modelValue', value );
	clearServerViolation();
}

function onNativeInput( event: Event ): void {
	unparseableInput.value = ( event.target as HTMLInputElement ).validity.badInput;
}

// Unparseable text outranks a server violation: the violation was raised against
// the value the backend was given, which is not what the field is showing.
const fieldMessages = computed<ValidationMessages>( () =>
	unparseableInput.value ?
		{ error: mw.message( 'neowiki-field-invalid-number' ).text() } :
		validationMessages.value
);

const fieldStatus = computed<'default' | 'error' | 'warning'>( () =>
	unparseableInput.value ? 'error' : validationStatus.value
);

const isInputEmpty = ( inputString: string ): boolean =>
	inputString === '' || isNaN( Number( inputString ) );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return isInputEmpty( internalInputValue.value ) ? undefined : newNumberValue( Number( internalInputValue.value ) );
	},
	hasUnparseableInput: (): boolean => unparseableInput.value
} );
</script>
