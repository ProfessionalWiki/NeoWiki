<template>
	<NeoNestedField :optional="true">
		<template #label>
			{{ $i18n( 'neowiki-property-editor-range' ).text() }}
		</template>

		<CdxField
			:status="minError === null ? 'default' : 'error'"
			:messages="minError === null ? {} : { error: minError }"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
			</template>

			<CdxTextInput
				:model-value="minInput"
				input-type="datetime-local"
				@update:model-value="updateMin"
			/>
		</CdxField>

		<CdxField
			:status="maxError === null ? 'default' : 'error'"
			:messages="maxError === null ? {} : { error: maxError }"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
			</template>

			<CdxTextInput
				:model-value="maxInput"
				input-type="datetime-local"
				@update:model-value="updateMax"
			/>
		</CdxField>
	</NeoNestedField>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import NeoNestedField from '@/components/common/NeoNestedField.vue';
import { fromLocalInputValue, toLocalInputValue } from '@/domain/propertyTypes/dateTimeConversion';

interface DateTimeRangeProperty extends PropertyDefinition {
	readonly minimum?: string;
	readonly maximum?: string;
}

const props = defineProps<{
	property: DateTimeRangeProperty;
}>();

const emit = defineEmits<{
	'update:property': [ Partial<DateTimeRangeProperty> ];
}>();

const minInput = ref( toLocalInputValue( props.property.minimum ) );
const maxInput = ref( toLocalInputValue( props.property.maximum ) );
const minError = ref<string | null>( null );
const maxError = ref<string | null>( null );

watch( () => props.property.minimum, ( value ) => {
	minInput.value = toLocalInputValue( value );
} );

watch( () => props.property.maximum, ( value ) => {
	maxInput.value = toLocalInputValue( value );
} );

// datetime-local wire values are always `YYYY-MM-DDTHH:mm` in a single
// timezone (host local), so lexicographic ordering matches chronological.
// The regex guards against malformed values bypassing the ordering check.
const DATETIME_LOCAL_WIRE_FORMAT = /^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}$/;

function dateTimeMinExceedsMax( min: string, max: string ): boolean {
	return DATETIME_LOCAL_WIRE_FORMAT.test( min ) &&
		DATETIME_LOCAL_WIRE_FORMAT.test( max ) &&
		min > max;
}

function updateMin( value: string ): void {
	minInput.value = value;
	if ( dateTimeMinExceedsMax( value, maxInput.value ) ) {
		minError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}
	minError.value = null;
	maxError.value = null;
	emit( 'update:property', { minimum: fromLocalInputValue( value ) } );
}

function updateMax( value: string ): void {
	maxInput.value = value;
	if ( dateTimeMinExceedsMax( minInput.value, value ) ) {
		maxError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}
	maxError.value = null;
	minError.value = null;
	emit( 'update:property', { maximum: fromLocalInputValue( value ) } );
}
</script>
