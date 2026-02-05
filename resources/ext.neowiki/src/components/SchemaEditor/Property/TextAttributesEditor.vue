<template>
	<!-- cdx-field class is used for spacing -->
	<div class="text-attributes cdx-field">
		<CdxField :hide-label="true">
			<CdxToggleSwitch
				:model-value="property.multiple"
				:align-switch="true"
				:label="$i18n( 'neowiki-property-editor-multiple' ).text()"
				@update:model-value="updateMultiple"
			>
				{{ $i18n( 'neowiki-property-editor-multiple' ).text() }}
			</CdxToggleSwitch>
		</CdxField>

		<CdxField
			v-if="property.multiple"
			:hide-label="true"
		>
			<CdxToggleSwitch
				:model-value="property.uniqueItems"
				:align-switch="true"
				:label="$i18n( 'neowiki-property-editor-unique-items' ).text()"
				@update:model-value="updateUniqueItems"
			>
				{{ $i18n( 'neowiki-property-editor-unique-items' ).text() }}
			</CdxToggleSwitch>
		</CdxField>

		<NeoNestedField :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-character-length' ).text() }}
			</template>

			<CdxField
				:status="minLengthError === null ? 'default' : 'error'"
				:messages="minLengthError === null ? {} : { error: minLengthError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<CdxTextInput
					:model-value="minLengthInput"
					input-type="number"
					min="1"
					@update:model-value="updateMinLength"
				/>
			</CdxField>

			<CdxField
				:status="maxLengthError === null ? 'default' : 'error'"
				:messages="maxLengthError === null ? {} : { error: maxLengthError }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<CdxTextInput
					:model-value="maxLengthInput"
					input-type="number"
					min="1"
					@update:model-value="updateMaxLength"
				/>
			</CdxField>
		</NeoNestedField>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { TextProperty } from '@/domain/propertyTypes/Text.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { CdxToggleSwitch, CdxField, CdxTextInput } from '@wikimedia/codex';
import NeoNestedField from '@/components/common/NeoNestedField.vue';

const props = defineProps<AttributesEditorProps<TextProperty>>();
const emit = defineEmits<AttributesEditorEmits<TextProperty>>();

const minLengthInput = ref( props.property.minLength?.toString() ?? '' );
const maxLengthInput = ref( props.property.maxLength?.toString() ?? '' );
const minLengthError = ref<string | null>( null );
const maxLengthError = ref<string | null>( null );

watch( () => props.property.minLength, ( newVal ) => {
	minLengthInput.value = newVal?.toString() ?? '';
} );

watch( () => props.property.maxLength, ( newVal ) => {
	maxLengthInput.value = newVal?.toString() ?? '';
} );

const isPositiveInteger = ( value: string ): boolean => {
	if ( value === '' ) {
		return true;
	}
	const num = Number( value );
	return Number.isInteger( num ) && num >= 1;
};

const validateValue = ( value: string ): string | null => {
	if ( value !== '' && !isPositiveInteger( value ) ) {
		return mw.message( 'neowiki-property-editor-length-whole-number' ).text();
	}
	return null;
};

const minExceedsMax = ( minValue: string, maxValue: string ): boolean => {
	const min = minValue === '' ? undefined : Number( minValue );
	const max = maxValue === '' ? undefined : Number( maxValue );
	return min !== undefined && max !== undefined && min > max;
};

const updateMinLength = ( value: string ): void => {
	minLengthInput.value = value;

	const formatError = validateValue( value );
	if ( formatError !== null ) {
		minLengthError.value = formatError;
		return;
	}

	if ( minExceedsMax( value, maxLengthInput.value ) ) {
		minLengthError.value = mw.message( 'neowiki-property-editor-length-min-exceeds-max' ).text();
		return;
	}

	minLengthError.value = null;
	maxLengthError.value = null;
	emit( 'update:property', { minLength: value === '' ? undefined : Number( value ) } );
};

const updateMaxLength = ( value: string ): void => {
	maxLengthInput.value = value;

	const formatError = validateValue( value );
	if ( formatError !== null ) {
		maxLengthError.value = formatError;
		return;
	}

	if ( minExceedsMax( minLengthInput.value, value ) ) {
		maxLengthError.value = mw.message( 'neowiki-property-editor-length-min-exceeds-max' ).text();
		return;
	}

	maxLengthError.value = null;
	minLengthError.value = null;
	emit( 'update:property', { maxLength: value === '' ? undefined : Number( value ) } );
};

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};

const updateUniqueItems = ( value: boolean ): void => {
	emit( 'update:property', { uniqueItems: value } );
};
</script>
