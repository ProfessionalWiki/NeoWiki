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
		<DateTextInput
			ref="textInput"
			:model-value="isoOf( props.modelValue )"
			:hint="hint"
			:picker="props.property.minPrecision === 'day'"
			@update:model-value="onInput"
		/>
	</CdxField>
</template>

<script lang="ts">
import type { Value } from '@/domain/Value';
</script>

<script setup lang="ts">
import { computed, ref, toRef } from 'vue';
import { CdxField, CdxIcon, type ValidationMessages } from '@wikimedia/codex';
import { cdxIconInfo } from '@wikimedia/codex-icons';
import { newStringValue, StringValue, ValueType } from '@/domain/Value';
import { DateProperty } from '@/domain/propertyTypes/Date.ts';
import { formatDateForDisplay } from '@/domain/propertyTypes/dateText.ts';
import { userLanguageTag } from '@/presentation/mediaWikiLanguages.ts';
import DateTextInput from '@/components/Value/DateTextInput.vue';
import { ValueInputEmits, ValueInputExposes, ValueInputProps } from '@/components/Value/ValueInputContract.ts';
import { useFieldServerViolation } from '@/composables/useFieldServerViolation.ts';
import { violationStatus } from '@/composables/useServerViolations.ts';

const props = withDefaults(
	defineProps<ValueInputProps<DateProperty>>(),
	{
		modelValue: undefined,
		label: ''
	}
);

const emit = defineEmits<ValueInputEmits>();

const language = userLanguageTag();

const { validationMessages, clearServerViolation } = useFieldServerViolation(
	toRef( props, 'property' ),
	toRef( props, 'serverViolations' ),
	emit,
	( iso: string ): string => formatDateForDisplay( iso, language )
);

const textInput = ref<InstanceType<typeof DateTextInput> | null>( null );

const isoOf = ( value: Value | undefined ): string | undefined =>
	value && value.type === ValueType.String ? ( value as StringValue ).parts[ 0 ] || undefined : undefined;

const valueOf = ( iso: string | undefined ): Value | undefined =>
	iso === undefined ? undefined : newStringValue( iso );

function onInput( iso: string | undefined ): void {
	emit( 'update:modelValue', valueOf( iso ) );
	clearServerViolation();
}

// Text that does not read as a date outranks a server violation: the violation was raised
// against the value the backend was given, which is not what the field is showing.
const fieldMessages = computed<ValidationMessages>( () => {
	const unreadable = textInput.value?.errorMessage() ?? null;

	return unreadable === null ? validationMessages.value : { error: unreadable };
} );

const fieldStatus = computed( () => violationStatus( fieldMessages.value ) );

const HINTS = {
	year: 'neowiki-date-input-hint-year',
	month: 'neowiki-date-input-hint-month',
	day: 'neowiki-date-input-hint-day'
};

// What the property asks for, for the field to say while nothing is typed; a message takes
// its place.
const hint = computed<string>( () => {
	if ( Object.keys( fieldMessages.value ).length > 0 ) {
		return '';
	}

	return mw.message( HINTS[ props.property.minPrecision ?? 'year' ] ).text();
} );

defineExpose<ValueInputExposes>( {
	getCurrentValue: (): Value | undefined => valueOf( textInput.value?.currentIso() ),
	unparseableInputMessage: (): string | null => textInput.value?.unreadableMessage() ?? null
} );
</script>
