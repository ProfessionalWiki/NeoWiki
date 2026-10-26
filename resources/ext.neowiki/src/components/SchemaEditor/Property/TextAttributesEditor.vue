<template>
	<!-- cdx-field class is used for spacing -->
	<div class="text-attributes cdx-field">
		<slot name="values">
			<CdxField :hide-label="true">
				<CdxCheckbox
					:model-value="property.multiple"
					@update:model-value="updateMultiple"
				>
					{{ $i18n( 'neowiki-property-editor-multiple' ).text() }}
				</CdxCheckbox>
			</CdxField>

			<CdxField
				v-if="property.multiple"
				class="text-attributes__unique-items ext-neowiki-severity-row"
				:hide-label="true"
			>
				<CdxCheckbox
					:model-value="property.uniqueItems"
					@update:model-value="updateUniqueItems"
				>
					{{ $i18n( 'neowiki-property-editor-unique-items' ).text() }}
				</CdxCheckbox>
				<SeverityInput
					v-if="property.uniqueItems"
					:constraint="$i18n( 'neowiki-property-editor-unique-items' ).text()"
					:model-value="property.constraintSeverities?.uniqueItems"
					@update:model-value="updateSeverity( 'uniqueItems', $event )"
				/>
			</CdxField>
		</slot>

		<NeoNestedField :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-character-length' ).text() }}
			</template>

			<CdxField
				class="text-attributes__min-length ext-neowiki-severity-field"
				:status="minLengthMessage === null ? 'default' : 'error'"
				:messages="minLengthMessage === null ? {} : { error: minLengthMessage }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<NeoNativeInput
					:model-value="minLengthInput"
					input-type="number"
					min="1"
					@update:model-value="updateMinLength"
					@unparseable-change="minLengthUnparseable = $event"
				/>
				<SeverityInput
					v-if="property.minLength !== undefined"
					:constraint="$i18n( 'neowiki-property-editor-minimum' ).text()"
					:model-value="property.constraintSeverities?.minLength"
					@update:model-value="updateSeverity( 'minLength', $event )"
				/>
			</CdxField>

			<CdxField
				class="text-attributes__max-length ext-neowiki-severity-field"
				:status="maxLengthMessage === null ? 'default' : 'error'"
				:messages="maxLengthMessage === null ? {} : { error: maxLengthMessage }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<NeoNativeInput
					:model-value="maxLengthInput"
					input-type="number"
					min="1"
					@update:model-value="updateMaxLength"
					@unparseable-change="maxLengthUnparseable = $event"
				/>
				<SeverityInput
					v-if="property.maxLength !== undefined"
					:constraint="$i18n( 'neowiki-property-editor-maximum' ).text()"
					:model-value="property.constraintSeverities?.maxLength"
					@update:model-value="updateSeverity( 'maxLength', $event )"
				/>
			</CdxField>
		</NeoNestedField>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { TextProperty } from '@/domain/propertyTypes/Text.ts';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { Severity } from '@/domain/Severity.ts';
import { AttributesEditorEmits, AttributesEditorExposes, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { CdxCheckbox, CdxField } from '@wikimedia/codex';
import { minExceedsMax } from '@/components/SchemaEditor/Property/minExceedsMax.ts';
import NeoNestedField from '@/components/common/NeoNestedField.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import NeoNativeInput from '@/components/common/NeoNativeInput.vue';

// Text with language has no uniqueItems: it puts its own control in the values slot.
type TextLikeProperty = Omit<TextProperty, 'uniqueItems'> & Partial<Pick<TextProperty, 'uniqueItems'>>;

const props = defineProps<AttributesEditorProps<TextLikeProperty>>();
const emit = defineEmits<AttributesEditorEmits<TextLikeProperty>>();

const minLengthInput = ref( props.property.minLength?.toString() ?? '' );
const maxLengthInput = ref( props.property.maxLength?.toString() ?? '' );
const minLengthError = ref<string | null>( null );
const maxLengthError = ref<string | null>( null );
const minLengthUnparseable = ref<string | null>( null );
const maxLengthUnparseable = ref<string | null>( null );

const minLengthMessage = computed( () => minLengthUnparseable.value ?? minLengthError.value );
const maxLengthMessage = computed( () => maxLengthUnparseable.value ?? maxLengthError.value );

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

const updateSeverity = ( constraint: 'minLength' | 'maxLength' | 'uniqueItems', severity: Severity ): void => {
	emit( 'update:property', withConstraintSeverity( props.property, constraint, severity ) );
};

defineExpose<AttributesEditorExposes>( {
	unparseableInputMessage: (): string | null => minLengthUnparseable.value ?? maxLengthUnparseable.value
} );
</script>
