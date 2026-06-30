<template>
	<CdxField
		:status="validationError === null ? 'default' : 'error'"
		:messages="validationError === null ? {} : { error: validationError }"
		:hide-label="!showFieldHeading"
	>
		<template
			v-if="showFieldHeading"
			#label
		>
			{{ props.label }}
		</template>
		<CdxCheckbox
			:model-value="internalValue"
			@update:model-value="onInput"
		>
			{{ props.property.name.toString() }}
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
import { computed, ref, toRef, watch } from 'vue';
import { CdxCheckbox, CdxField, CdxIcon } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { newBooleanValue, BooleanValue, ValueType } from '@/domain/Value';
import { BooleanProperty } from '@/domain/propertyTypes/Boolean.ts';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { useFieldServerViolation } from '@/composables/useFieldServerViolation.ts';

const props = withDefaults(
	defineProps<ValueInputProps<BooleanProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const { validationError, clearServerViolation } = useFieldServerViolation(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	emit
);

// Hide the heading when it would duplicate the inline checkbox label
// (subject-editor case: the caller passes the property name as the label).
const showFieldHeading = computed( () => props.label !== props.property.name.toString() );

const toBoolean = ( value: Value | undefined ): boolean =>
	value !== undefined && value.type === ValueType.Boolean ? ( value as BooleanValue ).boolean : false;

const internalValue = ref<boolean>( toBoolean( props.modelValue ) );

function onInput( newValue: boolean ): void {
	internalValue.value = newValue;
	const value = newBooleanValue( newValue );
	emit( 'update:modelValue', value );
	clearServerViolation();
}

watch( () => props.modelValue, ( newValue ) => {
	internalValue.value = toBoolean( newValue );
} );

defineExpose<ValueInputExposes>( {
	getCurrentValue: function(): Value | undefined {
		return newBooleanValue( internalValue.value );
	}
} );
</script>
