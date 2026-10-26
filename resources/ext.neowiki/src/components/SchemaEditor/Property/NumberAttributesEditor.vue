<template>
	<!-- cdx-field class is used for spacing -->
	<div class="number-attributes cdx-field">
		<NeoNestedField :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-range' ).text() }}
			</template>

			<CdxField
				class="number-attributes__minimum ext-neowiki-severity-field"
				:status="minimumMessage === null ? 'default' : 'error'"
				:messages="minimumMessage === null ? {} : { error: minimumMessage }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<NeoNativeInput
					:model-value="minimumInput"
					input-type="number"
					@update:model-value="updateMinimum"
					@unparseable-change="minimumUnparseable = $event"
				/>
				<SeverityInput
					v-if="property.minimum !== undefined"
					:constraint="$i18n( 'neowiki-property-editor-minimum' ).text()"
					:model-value="property.constraintSeverities?.minimum"
					@update:model-value="updateSeverity( 'minimum', $event )"
				/>
			</CdxField>

			<CdxField
				class="number-attributes__maximum ext-neowiki-severity-field"
				:status="maximumMessage === null ? 'default' : 'error'"
				:messages="maximumMessage === null ? {} : { error: maximumMessage }"
			>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<NeoNativeInput
					:model-value="maximumInput"
					input-type="number"
					@update:model-value="updateMaximum"
					@unparseable-change="maximumUnparseable = $event"
				/>
				<SeverityInput
					v-if="property.maximum !== undefined"
					:constraint="$i18n( 'neowiki-property-editor-maximum' ).text()"
					:model-value="property.constraintSeverities?.maximum"
					@update:model-value="updateSeverity( 'maximum', $event )"
				/>
			</CdxField>
		</NeoNestedField>

		<CdxField
			class="number-attributes__precision"
			:status="precisionMessage === null ? 'default' : 'error'"
			:messages="precisionMessage === null ? {} : { error: precisionMessage }"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-precision' ).text() }}
			</template>
			<NeoNativeInput
				:model-value="precisionInput"
				input-type="number"
				min="0"
				@update:model-value="updatePrecision"
				@unparseable-change="precisionUnparseable = $event"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { computed, ref, watch } from 'vue';
import { CdxField } from '@wikimedia/codex';
import { NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { Severity } from '@/domain/Severity.ts';
import { AttributesEditorEmits, AttributesEditorExposes, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { minExceedsMax } from '@/components/SchemaEditor/Property/minExceedsMax.ts';
import NeoNestedField from '@/components/common/NeoNestedField.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import NeoNativeInput from '@/components/common/NeoNativeInput.vue';

const props = defineProps<AttributesEditorProps<NumberProperty>>();
const emit = defineEmits<AttributesEditorEmits<NumberProperty>>();

const minimumInput = ref( props.property.minimum?.toString() ?? '' );
const maximumInput = ref( props.property.maximum?.toString() ?? '' );
const precisionInput = ref( props.property.precision?.toString() ?? '' );
const minimumError = ref<string | null>( null );
const maximumError = ref<string | null>( null );
const precisionError = ref<string | null>( null );
const minimumUnparseable = ref<string | null>( null );
const maximumUnparseable = ref<string | null>( null );
const precisionUnparseable = ref<string | null>( null );

const minimumMessage = computed( () => minimumUnparseable.value ?? minimumError.value );
const maximumMessage = computed( () => maximumUnparseable.value ?? maximumError.value );
const precisionMessage = computed( () => precisionUnparseable.value ?? precisionError.value );

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

const updateSeverity = ( constraint: 'minimum' | 'maximum', severity: Severity ): void => {
	emit( 'update:property', withConstraintSeverity( props.property, constraint, severity ) );
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

defineExpose<AttributesEditorExposes>( {
	unparseableInputMessage: (): string | null => minimumUnparseable.value ?? maximumUnparseable.value ?? precisionUnparseable.value
} );
</script>
