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
			<SchemaLookup
				:selected="property.targetSchema ?? null"
				@select="updateTargetSchema"
			/>
		</CdxField>

		<CdxField :hide-label="true">
			<CdxCheckbox
				:model-value="property.multiple ?? false"
				@update:model-value="updateMultiple"
			>
				{{ $i18n( 'neowiki-property-editor-multiple' ).text() }}
			</CdxCheckbox>
		</CdxField>
	</div>
</template>

<script setup lang="ts">
import { computed, onMounted, ref, watch } from 'vue';
import { CdxCheckbox, CdxField, CdxTextInput } from '@wikimedia/codex';
import { RelationProperty } from '@/domain/propertyTypes/Relation.ts';
import { AttributesEditorEmits, AttributesEditorProps } from '@/components/SchemaEditor/Property/AttributesEditorContract.ts';
import SchemaLookup from '@/components/common/SchemaLookup.vue';

const props = defineProps<AttributesEditorProps<RelationProperty>>();
const emit = defineEmits<AttributesEditorEmits<RelationProperty>>();

const relationInput = ref( props.property.relation || props.property.name.toString() );

watch( () => props.property.relation, ( newValue ) => {
	relationInput.value = newValue || props.property.name.toString();
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

const targetSchemaError = computed<string | null>( () =>
	( props.property.targetSchema ?? '' ).trim() === '' ?
		mw.message( 'neowiki-property-editor-target-schema-required' ).text() :
		null
);

const updateRelation = ( value: string ): void => {
	relationInput.value = value;
	const trimmed = value.trim();
	if ( trimmed !== '' ) {
		emit( 'update:property', { relation: trimmed } );
	}
};

const updateTargetSchema = ( schemaName: string ): void => {
	emit( 'update:property', { targetSchema: schemaName } );
};

const updateMultiple = ( value: boolean ): void => {
	emit( 'update:property', { multiple: value } );
};
</script>
