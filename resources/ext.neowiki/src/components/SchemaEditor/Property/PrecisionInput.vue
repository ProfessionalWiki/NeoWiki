<template>
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
</template>

<script setup lang="ts">
import { ref, watch } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import type { NumberProperty } from '@/domain/propertyTypes/Number';

const props = defineProps<{
	property: NumberProperty;
}>();

const emit = defineEmits<{
	'update:property': [ Partial<NumberProperty> ];
}>();

const precisionInput = ref( props.property.precision?.toString() ?? '' );
const precisionError = ref<string | null>( null );

watch( () => props.property.precision, ( value ) => {
	precisionInput.value = value?.toString() ?? '';
} );

function parseNumber( value: string ): number | undefined {
	return value === '' ? undefined : Number( value );
}

function isValidPrecision( value: number | undefined ): boolean {
	return value === undefined || ( Number.isInteger( value ) && value >= 0 );
}

function updatePrecision( value: string ): void {
	precisionInput.value = value;
	const parsed = parseNumber( value );
	if ( !isValidPrecision( parsed ) ) {
		precisionError.value = mw.message( 'neowiki-property-editor-precision-non-negative' ).text();
		return;
	}
	precisionError.value = null;
	emit( 'update:property', { precision: parsed } );
}
</script>
