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
			class="url-attributes__unique-items ext-neowiki-severity-row"
			:hide-label="true"
		>
			<CdxCheckbox
				:model-value="property.uniqueItems"
				@update:model-value="updateUniqueItems"
			>
				{{ $i18n( 'neowiki-property-editor-unique-items' ).text() }}
			</CdxCheckbox>
			<SeverityInput
				v-if="property.uniqueItems"
				:constraint="$i18n( 'neowiki-property-editor-unique-items' ).text()"
				:model-value="property.constraintSeverities?.uniqueItems"
				@update:model-value="updateSeverity"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { UrlProperty } from '@/domain/propertyTypes/Url.ts';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { Severity } from '@/domain/Severity.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { CdxCheckbox, CdxField } from '@wikimedia/codex';

const props = defineProps<AttributesEditorProps<UrlProperty>>();
const emit = defineEmits<AttributesEditorEmits<UrlProperty>>();

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};

const updateUniqueItems = ( value: boolean ): void => {
	emit( 'update:property', { uniqueItems: value } );
};

const updateSeverity = ( severity: Severity ): void => {
	emit( 'update:property', withConstraintSeverity( props.property, 'uniqueItems', severity ) );
};
</script>
