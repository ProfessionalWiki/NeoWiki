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

		<CdxField
			:status="lengthError === null ? 'default' : 'error'"
			:messages="lengthError === null ? {} : { error: lengthError }"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-length-constraint' ).text() }}
			</template>
			<div class="text-attributes__length-constraint">
				<span>{{ $i18n( 'neowiki-property-editor-length-between' ).text() }}</span>
				<CdxTextInput
					:model-value="minLengthInput"
					input-type="number"
					min="1"
					class="text-attributes__length-input"
					@update:model-value="updateMinLength"
				/>
				<span>{{ $i18n( 'neowiki-property-editor-length-and' ).text() }}</span>
				<CdxTextInput
					:model-value="maxLengthInput"
					input-type="number"
					min="1"
					class="text-attributes__length-input"
					@update:model-value="updateMaxLength"
				/>
				<span>{{ $i18n( 'neowiki-property-editor-length-characters' ).text() }}</span>
			</div>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { TextProperty } from '@/domain/propertyTypes/Text.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { CdxToggleSwitch, CdxField, CdxTextInput } from '@wikimedia/codex';

const props = defineProps<AttributesEditorProps<TextProperty>>();
const emit = defineEmits<AttributesEditorEmits<TextProperty>>();

const minLengthInput = ref( props.property.minLength?.toString() ?? '' );
const maxLengthInput = ref( props.property.maxLength?.toString() ?? '' );
const lengthError = ref<string | null>( null );

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

const validateLength = ( minValue: string, maxValue: string ): string | null => {
	if ( minValue !== '' && !isPositiveInteger( minValue ) ) {
		return mw.message( 'neowiki-property-editor-length-whole-number' ).text();
	}

	if ( maxValue !== '' && !isPositiveInteger( maxValue ) ) {
		return mw.message( 'neowiki-property-editor-length-whole-number' ).text();
	}

	const min = minValue === '' ? undefined : Number( minValue );
	const max = maxValue === '' ? undefined : Number( maxValue );
	if ( min !== undefined && max !== undefined && min > max ) {
		return mw.message( 'neowiki-property-editor-length-min-exceeds-max' ).text();
	}

	return null;
};

const updateMinLength = ( value: string ): void => {
	minLengthInput.value = value;
	const error = validateLength( value, maxLengthInput.value );

	if ( error === null ) {
		lengthError.value = null;
		emit( 'update:property', { minLength: value === '' ? undefined : Number( value ) } );
		return;
	}

	lengthError.value = error;
};

const updateMaxLength = ( value: string ): void => {
	maxLengthInput.value = value;
	const error = validateLength( minLengthInput.value, value );

	if ( error === null ) {
		lengthError.value = null;
		emit( 'update:property', { maxLength: value === '' ? undefined : Number( value ) } );
		return;
	}

	lengthError.value = error;
};

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};

const updateUniqueItems = ( value: boolean ): void => {
	emit( 'update:property', { uniqueItems: value } );
};
</script>

<style lang="less">
.text-attributes__length-constraint {
	display: flex;
	align-items: center;
	gap: 8px;
	flex-wrap: wrap;
}

.text-attributes__length-input.cdx-text-input {
	min-width: unset;
	width: 7em;
}
</style>
