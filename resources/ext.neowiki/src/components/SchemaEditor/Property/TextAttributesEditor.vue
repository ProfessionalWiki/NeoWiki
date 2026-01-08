<template>
	<!-- cdx-field class is used for spacing -->
	<div class="text-attributes cdx-field">
		<CdxField :hide-label="true">
			<CdxToggleSwitch
				:model-value="property.multiple"
				:align-switch="true"
				:label="$i18n( 'neowiki-property-editor-multiple' ).text()"
				@update:model-value="updateMultiple"
			>
				{{ $i18n( 'neowiki-property-editor-multiple' ).text() }}
			</CdxToggleSwitch>
		</CdxField>

		<CdxField
			v-if="property.multiple"
			:hide-label="true"
		>
			<CdxToggleSwitch
				:model-value="property.uniqueItems"
				:align-switch="true"
				:label="$i18n( 'neowiki-property-editor-unique-items' ).text()"
				@update:model-value="updateUniqueItems"
			>
				{{ $i18n( 'neowiki-property-editor-unique-items' ).text() }}
			</CdxToggleSwitch>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { MultiStringProperty } from '@/domain/PropertyDefinition.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { CdxToggleSwitch, CdxField } from '@wikimedia/codex';

defineProps<AttributesEditorProps<MultiStringProperty>>();
const emit = defineEmits<AttributesEditorEmits<MultiStringProperty>>();

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};

const updateUniqueItems = ( value: boolean ): void => {
	emit( 'update:property', { uniqueItems: value } );
};
</script>
