<template>
	<!-- cdx-field class is used for spacing -->
	<div class="relation-attributes cdx-field">
		<CdxField
			class="relation-attributes__target-schema"
			:status="targetSchemaError === null ? 'default' : 'error'"
			:messages="targetSchemaError === null ? {} : { error: targetSchemaError }"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-target-schema' ).text() }}
			</template>
			<SchemaPicker
				v-if="targetSchemaIsEditable"
				:selected="localTargetSchemaName || null"
				@select="updateTargetSchema"
			/>
			<CdxTextInput
				v-else
				:model-value="schemaReferenceName( property.targetSchema )"
				:disabled="true"
				input-type="text"
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
import { isLocalSchemaReference, schemaReferenceName } from '@/domain/SchemaReference';
import { computed } from 'vue';
import { CdxCheckbox, CdxField, CdxTextInput } from '@wikimedia/codex';
import { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { Severity } from '@/domain/Severity.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import SchemaPicker from '@/components/common/SchemaPicker.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';

const props = defineProps<AttributesEditorProps<RelationProperty>>();
const emit = defineEmits<AttributesEditorEmits<RelationProperty>>();

// A Schema of another Source is shown but not edited here: the picker offers this wiki's Schemas
// alone, and selecting one from it would replace a reference the editor cannot express (ADR 23).
// A property that has no target yet gets the picker, which is how it comes by one.
const targetSchemaIsEditable = computed<boolean>( () =>
	props.property.targetSchema === undefined || isLocalSchemaReference( props.property.targetSchema )
);

const localTargetSchemaName = computed<string>( () =>
	targetSchemaIsEditable.value ? schemaReferenceName( props.property.targetSchema ) : ''
);

const targetSchemaError = computed<string | null>( () =>
	schemaReferenceName( props.property.targetSchema ) === '' ?
		mw.message( 'neowiki-property-editor-target-schema-required' ).text() :
		null
);

const updateTargetSchema = ( schemaName: string ): void => {
	emit( 'update:property', { targetSchema: schemaName } );
};

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};

const updateSeverity = ( severity: Severity ): void => {
	emit( 'update:property', withConstraintSeverity( props.property, 'multiple', severity ) );
};
</script>
