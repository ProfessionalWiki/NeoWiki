<template>
	<NeoNestedField :optional="true">
		<template #label>
			{{ $i18n( 'neowiki-property-editor-character-length' ).text() }}
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
				input-type="number"
				min="1"
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
				input-type="number"
				min="1"
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
import { minExceedsMax } from '@/components/SchemaEditor/Property/minExceedsMax';

interface IntegerRangeProperty extends PropertyDefinition {
	readonly minLength?: number;
	readonly maxLength?: number;
}

const props = defineProps<{
	property: IntegerRangeProperty;
}>();

const emit = defineEmits<{
	'update:property': [ Partial<IntegerRangeProperty> ];
}>();

const minInput = ref( props.property.minLength?.toString() ?? '' );
const maxInput = ref( props.property.maxLength?.toString() ?? '' );
const minError = ref<string | null>( null );
const maxError = ref<string | null>( null );

watch( () => props.property.minLength, ( value ) => {
	minInput.value = value?.toString() ?? '';
} );

watch( () => props.property.maxLength, ( value ) => {
	maxInput.value = value?.toString() ?? '';
} );

function isPositiveInteger( value: string ): boolean {
	if ( value === '' ) {
		return true;
	}
	const n = Number( value );
	return Number.isInteger( n ) && n >= 1;
}

function formatErrorFor( value: string ): string | null {
	if ( value !== '' && !isPositiveInteger( value ) ) {
		return mw.message( 'neowiki-property-editor-length-whole-number' ).text();
	}
	return null;
}

function updateMin( value: string ): void {
	minInput.value = value;
	const formatErr = formatErrorFor( value );
	if ( formatErr !== null ) {
		minError.value = formatErr;
		return;
	}
	if ( minExceedsMax( value, maxInput.value ) ) {
		minError.value = mw.message( 'neowiki-property-editor-length-min-exceeds-max' ).text();
		return;
	}
	minError.value = null;
	maxError.value = null;
	emit( 'update:property', { minLength: value === '' ? undefined : Number( value ) } );
}

function updateMax( value: string ): void {
	maxInput.value = value;
	const formatErr = formatErrorFor( value );
	if ( formatErr !== null ) {
		maxError.value = formatErr;
		return;
	}
	if ( minExceedsMax( minInput.value, value ) ) {
		maxError.value = mw.message( 'neowiki-property-editor-length-min-exceeds-max' ).text();
		return;
	}
	maxError.value = null;
	minError.value = null;
	emit( 'update:property', { maxLength: value === '' ? undefined : Number( value ) } );
}
</script>
