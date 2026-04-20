<template>
	<div class="datetime-attributes cdx-field">
		<NeoNestedField :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-range' ).text() }}
			</template>

			<CdxField
				class="datetime-attributes__minimum"
				:status="minimumError === null ? 'default' : 'error'"
				:messages="minimumError === null ? {} : { error: minimumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<CdxTextInput
					input-type="datetime-local"
					:model-value="minimumInput"
					@update:model-value="updateMinimum"
				/>
			</CdxField>

			<CdxField
				class="datetime-attributes__maximum"
				:status="maximumError === null ? 'default' : 'error'"
				:messages="maximumError === null ? {} : { error: maximumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<CdxTextInput
					input-type="datetime-local"
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
import { DateTimeProperty } from '@/domain/propertyTypes/DateTime.ts';
import { fromLocalInputValue, toLocalInputValue } from '@/domain/propertyTypes/dateTimeConversion.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import NeoNestedField from '@/components/common/NeoNestedField.vue';

const props = defineProps<AttributesEditorProps<DateTimeProperty>>();
const emit = defineEmits<AttributesEditorEmits<DateTimeProperty>>();

const minimumInput = ref( toLocalInputValue( props.property.minimum ) );
const maximumInput = ref( toLocalInputValue( props.property.maximum ) );
const minimumError = ref<string | null>( null );
const maximumError = ref<string | null>( null );

watch( () => props.property.minimum, ( newVal ) => {
	minimumInput.value = toLocalInputValue( newVal );
} );

watch( () => props.property.maximum, ( newVal ) => {
	maximumInput.value = toLocalInputValue( newVal );
} );

function minExceedsMax( min: string, max: string ): boolean {
	if ( min === '' || max === '' ) {
		return false;
	}
	const minTime = Date.parse( min );
	const maxTime = Date.parse( max );
	return !Number.isNaN( minTime ) && !Number.isNaN( maxTime ) && minTime > maxTime;
}

const updateMinimum = ( value: string ): void => {
	minimumInput.value = value;

	if ( minExceedsMax( value, maximumInput.value ) ) {
		minimumError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}

	minimumError.value = null;
	maximumError.value = null;
	emit( 'update:property', { minimum: fromLocalInputValue( value ) } );
};

const updateMaximum = ( value: string ): void => {
	maximumInput.value = value;

	if ( minExceedsMax( minimumInput.value, value ) ) {
		maximumError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}

	maximumError.value = null;
	minimumError.value = null;
	emit( 'update:property', { maximum: fromLocalInputValue( value ) } );
};
</script>
