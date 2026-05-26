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
			input-type="date"
			:start-icon="cdxIconCalendar"
			:model-value="internalInputValue"
			:min="toDateInputValue( props.property.minimum )"
			:max="toDateInputValue( props.property.maximum )"
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
import { cdxIconInfo, cdxIconCalendar } from '@wikimedia/codex-icons';
import { newStringValue, StringValue, ValueType } from '@/domain/Value';
import { DateType, DateProperty, formatDateForDisplay } from '@/domain/propertyTypes/Date.ts';
import { fromDateInputValue, toDateInputValue } from '@/domain/propertyTypes/dateConversion.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = withDefaults(
	defineProps<ValueInputProps<DateProperty>>(),
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
		internalInputValue.value = str ? toDateInputValue( str ) : '';
	} else {
		internalInputValue.value = '';
	}
};

initializeInputValue( props.modelValue );

watch( () => props.modelValue, ( newValue ) => {
	initializeInputValue( newValue );
	validate( newValue && newValue.type === ValueType.String ? newValue as StringValue : undefined );
} );

const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( DateType.typeName );

function onInput( newValue: string ): void {
	internalInputValue.value = newValue;
	const isoValue = fromDateInputValue( newValue );
	const value = isoValue !== undefined ? newStringValue( isoValue ) : undefined;
	emit( 'update:modelValue', value );
	validate( value );
}

function validate( value: StringValue | undefined ): void {
	const errors = propertyType.validate( value, props.property );
	if ( errors.length === 0 ) {
		validationError.value = null;
		return;
	}
	const error = errors[ 0 ];
	const formattedArgs = ( ( error.args ?? [] ) as string[] ).map( formatDateForDisplay );
	validationError.value = mw.message( `neowiki-field-${ error.code }`, ...formattedArgs ).text();
}

watch( () => props.property, () => {
	validate( props.modelValue && props.modelValue.type === ValueType.String ? props.modelValue as StringValue : undefined );
} );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		const isoValue = fromDateInputValue( internalInputValue.value );
		return isoValue !== undefined ? newStringValue( isoValue ) : undefined;
	}
} );

validate( props.modelValue && props.modelValue.type === ValueType.String ? props.modelValue as StringValue : undefined );
</script>
