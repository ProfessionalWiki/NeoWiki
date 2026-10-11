<template>
	<!-- cdx-field class is used for spacing -->
	<div class="relation-attributes cdx-field">
		<CdxField
			:status="targetSchemasError === null ? 'default' : 'error'"
			:messages="targetSchemasError === null ? {} : { error: targetSchemasError }"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-target-schema' ).text() }}
			</template>
			<TargetSchemasInput
				:model-value="property.targetSchemas"
				@update:model-value="updateTargetSchemas"
			/>
		</CdxField>

		<CdxField
			class="relation-attributes__multiple ext-neowiki-severity-row"
			:hide-label="true"
		>
			<CdxCheckbox
				:model-value="property.multiple ?? false"
				@update:model-value="updateMultiple"
			>
				{{ $i18n( 'neowiki-property-editor-multiple' ).text() }}
			</CdxCheckbox>
			<SeverityInput
				v-if="!property.multiple"
				:constraint="$i18n( 'neowiki-property-editor-single-value' ).text()"
				:model-value="property.constraintSeverities?.multiple"
				@update:model-value="updateSeverity"
			/>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { computed } from 'vue';
import { CdxCheckbox, CdxField } from '@wikimedia/codex';
import { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { SchemaReference } from '@/domain/SchemaReference';
import type { Severity } from '@/domain/Severity.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { missingRelationAttribute } from '@/components/SchemaEditor/Property/missingRelationAttribute.ts';
import TargetSchemasInput from '@/components/SchemaEditor/Property/TargetSchemasInput.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';

const props = defineProps<AttributesEditorProps<RelationProperty>>();
const emit = defineEmits<AttributesEditorEmits<RelationProperty>>();

const targetSchemasError = computed<string | null>( () => {
	const message = missingRelationAttribute( props.property );

	return message === null ? null : mw.message( message ).text();
} );

const updateTargetSchemas = ( targetSchemas: SchemaReference[] ): void => {
	emit( 'update:property', { targetSchemas } );
};

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};

const updateSeverity = ( severity: Severity ): void => {
	emit( 'update:property', withConstraintSeverity( props.property, 'multiple', severity ) );
};
</script>
