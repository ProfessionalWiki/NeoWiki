<template>
	<!-- cdx-field class is used for spacing -->
	<div class="number-attributes cdx-field">
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
		<CdxField>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-precision' ).text() }}
			</template>
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
import { NumberProperty } from '@/domain/propertyTypes/Number.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';

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
