<template>
	<div class="date-attributes cdx-field">
		<NeoNestedField :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-range' ).text() }}
			</template>

			<CdxField
				class="date-attributes__minimum"
				:status="minimumError === null ? 'default' : 'error'"
				:messages="minimumError === null ? {} : { error: minimumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<CdxTextInput
					input-type="date"
					:model-value="minimumInput"
					@update:model-value="updateMinimum"
				/>
			</CdxField>

			<CdxField
				class="date-attributes__maximum"
				:status="maximumError === null ? 'default' : 'error'"
				:messages="maximumError === null ? {} : { error: maximumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<CdxTextInput
					input-type="date"
					:model-value="maximumInput"
					@update:model-value="updateMaximum"
				/>
			</CdxField>
		</NeoNestedField>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { DateProperty } from '@/domain/propertyTypes/Date.ts';
import { fromDateInputValue, toDateInputValue } from '@/domain/propertyTypes/dateConversion.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import NeoNestedField from '@/components/common/NeoNestedField.vue';

const props = defineProps<AttributesEditorProps<DateProperty>>();
const emit = defineEmits<AttributesEditorEmits<DateProperty>>();

const minimumInput = ref( toDateInputValue( props.property.minimum ) );
const maximumInput = ref( toDateInputValue( props.property.maximum ) );
const minimumError = ref<string | null>( null );
const maximumError = ref<string | null>( null );

watch( () => props.property.minimum, ( newVal ) => {
	minimumInput.value = toDateInputValue( newVal );
} );

watch( () => props.property.maximum, ( newVal ) => {
	maximumInput.value = toDateInputValue( newVal );
} );

// `date` input wire values are always `YYYY-MM-DD`, so lexicographic ordering
// matches chronological ordering. The regex guards against malformed values
// bypassing the ordering check.
const DATE_WIRE_FORMAT = /^\d{4}-\d{2}-\d{2}$/;

function minExceedsMax( min: string, max: string ): boolean {
	return DATE_WIRE_FORMAT.test( min ) &&
		DATE_WIRE_FORMAT.test( max ) &&
		min > max;
}

const updateMinimum = ( value: string ): void => {
	minimumInput.value = value;

	if ( minExceedsMax( value, maximumInput.value ) ) {
		minimumError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}

	minimumError.value = null;
	maximumError.value = null;
	emit( 'update:property', { minimum: fromDateInputValue( value ) } );
};

const updateMaximum = ( value: string ): void => {
	maximumInput.value = value;

	if ( minExceedsMax( minimumInput.value, value ) ) {
		maximumError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}

	maximumError.value = null;
	minimumError.value = null;
	emit( 'update:property', { maximum: fromDateInputValue( value ) } );
};
</script>
