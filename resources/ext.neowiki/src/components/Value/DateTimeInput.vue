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
			input-type="datetime-local"
			:start-icon="cdxIconClock"
			:model-value="internalInputValue"
			:min="toLocalInputValue( props.property.minimum )"
			:max="toLocalInputValue( props.property.maximum )"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@/domain/Value';
</script>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField, CdxIcon, CdxTextInput } from '@wikimedia/codex';
import { cdxIconInfo, cdxIconClock } from '@wikimedia/codex-icons';
import { newStringValue, StringValue, ValueType } from '@/domain/Value';
import { DateTimeType, DateTimeProperty } from '@/domain/propertyTypes/DateTime.ts';
import { fromLocalInputValue, toLocalInputValue } from '@/domain/propertyTypes/dateTimeConversion.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = withDefaults(
	defineProps<ValueInputProps<DateTimeProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const validationError = ref<string | null>( null );
const internalInputValue = ref<string>( '' );

const initializeInputValue = ( value: Value | undefined ): void => {
	if ( value && value.type === ValueType.String ) {
		const str = ( value as StringValue ).parts[ 0 ];
		internalInputValue.value = str ? toLocalInputValue( str ) : '';
	} else {
		internalInputValue.value = '';
	}
};

initializeInputValue( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	initializeInputValue( newValue );
	validate( newValue && newValue.type === ValueType.String ? newValue as StringValue : undefined );
} );

const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( DateTimeType.typeName );

function onInput( newValue: string ): void {
	internalInputValue.value = newValue;
	const isoValue = fromLocalInputValue( newValue );
	const value = isoValue !== undefined ? newStringValue( isoValue ) : undefined;
	emit( 'update:modelValue', value );
	validate( value );
}

function validate( value: StringValue | undefined ): void {
	const errors = propertyType.validate( value, props.property );
	validationError.value = errors.length === 0 ? null :
		mw.message( `neowiki-field-${ errors[ 0 ].code }`, ...( errors[ 0 ].args ?? [] ) ).text();
}

watch( () => props.property, () => {
	validate( props.modelValue && props.modelValue.type === ValueType.String ? props.modelValue as StringValue : undefined );
} );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		const isoValue = fromLocalInputValue( internalInputValue.value );
		return isoValue !== undefined ? newStringValue( isoValue ) : undefined;
	}
} );

validate( props.modelValue && props.modelValue.type === ValueType.String ? props.modelValue as StringValue : undefined );
</script>
