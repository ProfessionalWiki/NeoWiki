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
				input-type="number"
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

interface NumericRangeProperty extends PropertyDefinition {
	readonly minimum?: number;
	readonly maximum?: number;
}

const props = defineProps<{
	property: NumericRangeProperty;
}>();

const emit = defineEmits<{
	'update:property': [ Partial<NumericRangeProperty> ];
}>();

const minInput = ref( props.property.minimum?.toString() ?? '' );
const maxInput = ref( props.property.maximum?.toString() ?? '' );
const minError = ref<string | null>( null );
const maxError = ref<string | null>( null );

watch( () => props.property.minimum, ( value ) => {
	minInput.value = value?.toString() ?? '';
} );

watch( () => props.property.maximum, ( value ) => {
	maxInput.value = value?.toString() ?? '';
} );

function parseNumber( value: string ): number | undefined {
	return value === '' ? undefined : Number( value );
}

function updateMin( value: string ): void {
	minInput.value = value;
	if ( minExceedsMax( value, maxInput.value ) ) {
		minError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}
	minError.value = null;
	maxError.value = null;
	emit( 'update:property', { minimum: parseNumber( value ) } );
}

function updateMax( value: string ): void {
	maxInput.value = value;
	if ( minExceedsMax( minInput.value, value ) ) {
		maxError.value = mw.message( 'neowiki-property-editor-min-exceeds-max' ).text();
		return;
	}
	maxError.value = null;
	minError.value = null;
	emit( 'update:property', { maximum: parseNumber( value ) } );
}
</script>
