<template>
	<!-- cdx-field class is used for spacing -->
	<div class="url-attributes cdx-field">
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
			:hide-label="true"
		>
			<CdxCheckbox
				:model-value="property.uniqueItems"
				@update:model-value="updateUniqueItems"
			>
				{{ $i18n( 'neowiki-property-editor-unique-items' ).text() }}
			</CdxCheckbox>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { UrlProperty } from '@/domain/propertyTypes/Url.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { CdxCheckbox, CdxField } from '@wikimedia/codex';

defineProps<AttributesEditorProps<UrlProperty>>();
const emit = defineEmits<AttributesEditorEmits<UrlProperty>>();

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};

const updateUniqueItems = ( value: boolean ): void => {
	emit( 'update:property', { uniqueItems: value } );
};
</script>
