<template>
	<!-- cdx-field class is used for spacing -->
	<div class="number-attributes cdx-field">
		<NeoNestedField :optional="true">
			<template #label>
				{{ $i18n( 'neowiki-property-editor-range' ).text() }}
			</template>

			<CdxField>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-minimum' ).text() }}
				</template>

				<CdxTextInput
					:model-value="property.minimum?.toString()"
					input-type="number"
					@update:model-value="updateMinimum"
				/>
			</CdxField>

			<CdxField>
				<template #label>
					{{ $i18n( 'neowiki-property-editor-maximum' ).text() }}
				</template>

				<CdxTextInput
					:model-value="property.maximum?.toString()"
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
				:model-value="property.precision?.toString()"
				input-type="number"
				min="0"
				@update:model-value="updatePrecision"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { ref } from 'vue';
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import NeoNestedField from '@/components/common/NeoNestedField.vue';

defineProps<AttributesEditorProps<NumberProperty>>();
const emit = defineEmits<AttributesEditorEmits<NumberProperty>>();

const precisionError = ref<string | null>( null );

const parseNumber = ( value: string ): number | undefined =>
	value ? Number( value ) : undefined;

const isValidPrecision = ( value: number | undefined ): boolean =>
	value === undefined || value >= 0;

const updateMinimum = ( value: string ): void => {
	emit( 'update:property', { minimum: parseNumber( value ) } );
};

const updateMaximum = ( value: string ): void => {
	emit( 'update:property', { maximum: parseNumber( value ) } );
};

const updatePrecision = ( value: string ): void => {
	const numValue = parseNumber( value );

	if ( isValidPrecision( numValue ) ) {
		precisionError.value = null;
		emit( 'update:property', { precision: numValue } );
		return;
	}

	precisionError.value = mw.message( 'neowiki-property-editor-precision-non-negative' ).text();
};
</script>
