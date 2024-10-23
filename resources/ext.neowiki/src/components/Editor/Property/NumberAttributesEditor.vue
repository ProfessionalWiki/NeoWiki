<template>
	<div class="number-attributes">
		<CdxField
			:label="$i18n( 'neowiki-property-editor-minimum' ).text()"
		>
			<CdxTextInput
				:model-value="property.minimum?.toString()"
				input-type="number"
				@update:model-value="updateMinimum"
			/>
		</CdxField>
		<CdxField
			:label="$i18n( 'neowiki-property-editor-maximum' ).text()"
		>
			<CdxTextInput
				:model-value="property.maximum?.toString()"
				input-type="number"
				@update:model-value="updateMaximum"
			/>
		</CdxField>
		<CdxField
			:label="$i18n( 'neowiki-property-editor-precision' ).text()"
		>
			<CdxTextInput
				:model-value="property.precision?.toString()"
				input-type="number"
				@update:model-value="updatePrecision"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { CdxField, CdxTextInput } from '@wikimedia/codex';
import { NumberProperty } from '@neo/domain/valueFormats/Number.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/Editor/Property/AttributesEditorContract.ts';

defineProps<AttributesEditorProps<NumberProperty>>();
const emit = defineEmits<AttributesEditorEmits<NumberProperty>>();

const updateMinimum = ( value: string ): void => {
	emit( 'update:property', { minimum: value ? Number( value ) : undefined } );
};

const updateMaximum = ( value: string ): void => {
	emit( 'update:property', { maximum: value ? Number( value ) : undefined } );
};

const updatePrecision = ( value: string ): void => {
	emit( 'update:property', { precision: value ? Number( value ) : undefined } );
};
</script>
