<template>
	<CdxField
		:status="validationError === null ? 'default' : 'error'"
		:messages="validationError === null ? {} : { error: validationError }"
		:hide-label="true"
	>
		<CdxCheckbox
			:model-value="internalValue"
			@update:model-value="onInput"
		>
			{{ props.label }}
			<CdxIcon
				v-if="props.property.description"
				v-tooltip="props.property.description"
				:icon="cdxIconInfo"
				class="ext-neowiki-value-input__description-icon"
				size="small"
			/>
		</CdxCheckbox>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@/domain/Value';
</script>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxCheckbox, CdxField, CdxIcon } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { newBooleanValue, BooleanValue, ValueType } from '@/domain/Value';
import { BooleanType, BooleanProperty } from '@/domain/propertyTypes/Boolean.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { NeoWikiServices } from '@/NeoWikiServices.ts';

const props = withDefaults(
	defineProps<ValueInputProps<BooleanProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const validationError = ref<string | null>( null );

const toBoolean = ( value: Value | undefined ): boolean =>
	value !== undefined && value.type === ValueType.Boolean ? ( value as BooleanValue ).boolean : false;

const internalValue = ref<boolean>( toBoolean( props.modelValue ) );

const propertyType = NeoWikiServices.getPropertyTypeRegistry().getType( BooleanType.typeName );

function validate( value: BooleanValue ): void {
	const errors = propertyType.validate( value, props.property );
	validationError.value = errors.length === 0 ? null :
		mw.message( `neowiki-field-${ errors[ 0 ].code }`, ...( errors[ 0 ].args ?? [] ) ).text();
}

function onInput( newValue: boolean ): void {
	internalValue.value = newValue;
	const value = newBooleanValue( newValue );
	emit( 'update:modelValue', value );
	validate( value );
}

watch( () => props.modelValue, ( newValue ) => {
	internalValue.value = toBoolean( newValue );
	validate( newBooleanValue( internalValue.value ) );
} );

watch( () => props.property, () => {
	validate( newBooleanValue( internalValue.value ) );
} );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return newBooleanValue( internalValue.value );
	}
} );

validate( newBooleanValue( internalValue.value ) );
</script>
