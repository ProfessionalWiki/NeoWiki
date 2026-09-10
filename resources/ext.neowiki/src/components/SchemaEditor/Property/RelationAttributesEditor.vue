<template>
	<!-- cdx-field class is used for spacing -->
	<div class="relation-attributes cdx-field">
		<CdxField
			class="relation-attributes__relation"
			:status="relationError === null ? 'default' : 'error'"
			:messages="relationError === null ? {} : { error: relationError }"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-relation' ).text() }}
			</template>
			<CdxTextInput
				:model-value="relationInput"
				input-type="text"
				@update:model-value="updateRelation"
			/>
		</CdxField>

		<CdxField
			class="relation-attributes__target-schema"
			:status="targetSchemaError === null ? 'default' : 'error'"
			:messages="targetSchemaError === null ? {} : { error: targetSchemaError }"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-target-schema' ).text() }}
			</template>
			<SchemaPicker
				v-if="targetSchemaIsLocal"
				:selected="localTargetSchemaName || null"
				@select="updateTargetSchema"
				@blur="targetSchemaTouched = true"
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
import { computed, onMounted, ref, watch } from 'vue';
import { CdxCheckbox, CdxField, CdxTextInput } from '@wikimedia/codex';
import { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { Severity } from '@/domain/Severity.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import SchemaPicker from '@/components/common/SchemaPicker.vue';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';

const props = defineProps<AttributesEditorProps<RelationProperty>>();
const emit = defineEmits<AttributesEditorEmits<RelationProperty>>();

const relationInput = ref( props.property.relation || props.property.name.toString() );

watch( () => props.property.relation, ( newValue ) => {
	relationInput.value = newValue;
} );

onMounted( () => {
	if ( !props.property.relation ) {
		emit( 'update:property', { relation: props.property.name.toString() } );
	}
} );

const relationError = computed<string | null>( () =>
	relationInput.value.trim() === '' ?
		mw.message( 'neowiki-property-editor-relation-required' ).text() :
		null
);

const targetSchemaTouched = ref( false );

// A Schema of another Source is shown but not edited here: the picker offers this wiki's Schemas
// alone, and selecting one from it would replace a reference the editor cannot express (ADR 23).
const targetSchemaIsLocal = computed<boolean>( () =>
	isLocalSchemaReference( props.property.targetSchema )
);

const localTargetSchemaName = computed<string>( () =>
	targetSchemaIsLocal.value ? schemaReferenceName( props.property.targetSchema ) : ''
);

const targetSchemaError = computed<string | null>( () =>
	targetSchemaTouched.value && schemaReferenceName( props.property.targetSchema ).trim() === '' ?
		mw.message( 'neowiki-property-editor-target-schema-required' ).text() :
		null
);

const updateRelation = ( value: string ): void => {
	relationInput.value = value;
	emit( 'update:property', { relation: value.trim() } );
};

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
