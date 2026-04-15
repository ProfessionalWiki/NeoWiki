<template>
	<!-- cdx-field class is used for spacing -->
	<div class="number-attributes cdx-field">
		<NeoNestedField :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-range' ).text() }}
			</template>

			<CdxField
				class="number-attributes__minimum"
				:status="minimumError === null ? 'default' : 'error'"
				:messages="minimumError === null ? {} : { error: minimumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<CdxTextInput
					:model-value="minimumInput"
					input-type="number"
					@update:model-value="updateMinimum"
				/>
			</CdxField>

			<CdxField
				class="number-attributes__maximum"
				:status="maximumError === null ? 'default' : 'error'"
				:messages="maximumError === null ? {} : { error: maximumError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<CdxTextInput
					:model-value="maximumInput"
					input-type="number"
					@update:model-value="updateMaximum"
				/>
			</CdxField>
		</NeoNestedField>

		<CdxField
			class="number-attributes__precision"
			:status="precisionError === null ? 'default' : 'error'"
			:messages="precisionError === null ? {} : { error: precisionError }"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-precision' ).text() }}
			</template>
			<CdxTextInput
				:model-value="precisionInput"
				input-type="number"
				min="0"
				@update:model-value="updatePrecision"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { minExceedsMax } from '@/components/SchemaEditor/Property/minExceedsMax.ts';
import NeoNestedField from '@/components/common/NeoNestedField.vue';

const props = defineProps<AttributesEditorProps<NumberProperty>>();
const emit = defineEmits<AttributesEditorEmits<NumberProperty>>();

const minimumInput = ref( props.property.minimum?.toString() ?? '' );
const maximumInput = ref( props.property.maximum?.toString() ?? '' );
const precisionInput = ref( props.property.precision?.toString() ?? '' );
const minimumError = ref<string | null>( null );
const maximumError = ref<string | null>( null );
const precisionError = ref<string | null>( null );

watch( () => props.property.minimum, ( newVal ) => {
	minimumInput.value = newVal?.toString() ?? '';
} );

watch( () => props.property.maximum, ( newVal ) => {
	maximumInput.value = newVal?.toString() ?? '';
} );

watch( () => props.property.precision, ( newVal ) => {
	precisionInput.value = newVal?.toString() ?? '';
} );

const parseNumber = ( value: string ): number | undefined =>
	value ? Number( value ) : undefined;

const isValidPrecision = ( value: number | undefined ): boolean =>
	value === undefined || value >= 0;

const updateMinimum = ( value: string ): void => {
	minimumInput.value = value;

	if ( minExceedsMax( value, maximumInput.value ) ) {
		minimumError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}

	minimumError.value = null;
	maximumError.value = null;
	emit( 'update:property', { minimum: parseNumber( value ) } );
};

const updateMaximum = ( value: string ): void => {
	maximumInput.value = value;

	if ( minExceedsMax( minimumInput.value, value ) ) {
		maximumError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}

	maximumError.value = null;
	minimumError.value = null;
	emit( 'update:property', { maximum: parseNumber( value ) } );
};

const updatePrecision = ( value: string ): void => {
	precisionInput.value = value;
	const numValue = parseNumber( value );

	if ( isValidPrecision( numValue ) ) {
		precisionError.value = null;
		emit( 'update:property', { precision: numValue } );
		return;
	}

	precisionError.value = mw.message( 'neowiki-property-editor-precision-non-negative' ).text();
};
</script>
