<template>
	<div class="text-attributes">
		<CdxCheckbox
			:model-value="property.multiple"
			:label="$i18n( 'neowiki-property-editor-multiple' ).text()"
			@update:model-value="updateMultiple"
		>
			<small>{{ $i18n( 'neowiki-property-editor-multiple' ).text() }}</small>
		</CdxCheckbox>

		<CdxCheckbox
			v-if="property.multiple"
			:model-value="property.uniqueItems"
			:label="$i18n( 'neowiki-property-editor-unique-items' ).text()"
			@update:model-value="updateUniqueItems"
		>
			<small>{{ $i18n( 'neowiki-property-editor-unique-items' ).text() }}</small>
		</CdxCheckbox>
	</div>
</template>

<script setup lang="ts">
import { MultiStringProperty } from '@neo/domain/PropertyDefinition.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/Editor/Property/AttributesEditorContract.ts';
import { CdxCheckbox } from '@wikimedia/codex';

defineProps<AttributesEditorProps<MultiStringProperty>>();
const emit = defineEmits<AttributesEditorEmits<MultiStringProperty>>();

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};

const updateUniqueItems = ( value: boolean ): void => {
	emit( 'update:property', { uniqueItems: value } );
};
</script>
