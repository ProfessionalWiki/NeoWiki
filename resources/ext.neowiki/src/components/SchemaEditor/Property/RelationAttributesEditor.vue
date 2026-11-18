<template>
	<!-- cdx-field class is used for spacing -->
	<div class="relation-attributes cdx-field">
		<CdxField
			:status="targetSchemasError === null ? 'default' : 'error'"
			:messages="targetSchemasError === null ? {} : { error: targetSchemasError }"
			@focusout="targetSchemasTouched = true"
		>
			<template #label>
				{{ $i18n( 'neowiki-property-editor-target-schema' ).text() }}
			</template>
			<TargetSchemasInput
				:model-value="property.targetSchemas"
				:offers-creation="canCreateSchemas && schemaCreation !== undefined"
				@update:model-value="updateTargetSchemas"
				@create="createTargetSchema"
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
import { computed, inject, onMounted, ref } from 'vue';
import { CdxCheckbox, CdxField } from '@wikimedia/codex';
import { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { withConstraintSeverity } from '@/domain/PropertyDefinition.ts';
import type { SchemaReference } from '@/domain/SchemaReference';
import type { Severity } from '@/domain/Severity.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import { missingRelationAttribute } from '@/components/SchemaEditor/Property/missingRelationAttribute.ts';
import TargetSchemasInput from '@/components/SchemaEditor/Property/TargetSchemasInput.vue';
import { SchemaCreationKey } from '@/components/common/SchemaCreation.ts';
import { ErrorsRevealedKey } from '@/components/SchemaEditor/ErrorsRevealed.ts';
import SeverityInput from '@/components/SchemaEditor/Property/SeverityInput.vue';
import { useSchemaPermissions } from '@/composables/useSchemaPermissions.ts';
import { normalizeSchemaName } from '@/stores/SchemaStore.ts';

const props = defineProps<AttributesEditorProps<RelationProperty>>();
const emit = defineEmits<AttributesEditorEmits<RelationProperty>>();

const { canCreateSchemas, checkCreatePermission } = useSchemaPermissions();

onMounted( checkCreatePermission );

const schemaCreation = inject( SchemaCreationKey, undefined );

const targetSchemasTouched = ref( false );
const errorsRevealed = inject( ErrorsRevealedKey, ref( false ) );

const targetSchemasError = computed<string | null>( () => {
	const message = missingRelationAttribute( props.property );

	return message !== null && ( targetSchemasTouched.value || errorsRevealed.value ) ?
		mw.message( message ).text() :
		null;
} );

const updateTargetSchemas = ( targetSchemas: SchemaReference[] ): void => {
	emit( 'update:property', { targetSchemas } );
};

async function createTargetSchema( schemaName: string, subjectsAreDependent: boolean | null ): Promise<void> {
	const schema = await schemaCreation?.create( schemaName, subjectsAreDependent ) ?? null;

	// The wiki saved the Schema under its page title, which is the name the field would list it by.
	if ( schema !== null ) {
		updateTargetSchemas( [ ...props.property.targetSchemas, normalizeSchemaName( schema.getName() ) ] );
	}
}

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};

const updateSeverity = ( severity: Severity ): void => {
	emit( 'update:property', withConstraintSeverity( props.property, 'multiple', severity ) );
};
</script>
