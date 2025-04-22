<template>
	<div v-if="originalSchema !== undefined" class="ext-neowiki-edit-schema-action">
		<SchemaEditor
			:schema-name="originalSchema.getName()"
			:description="originalSchema.getDescription()"
			:properties="Object.values( originalSchema.getPropertyDefinitions().asRecord() )"
			@update:schema="handleSchemaUpdate"
		/>

		<CdxTextArea />

		<CdxButton
			action="progressive"
			weight="primary"
			:disabled="updatedSchema === undefined || schemaEquals( originalSchema, updatedSchema )"
			@click="saveSchema"
		>
			<CdxIcon :icon="cdxIconCheck" />
			{{ $i18n( 'neowiki-save-schema' ).text() }}
		</CdxButton>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import { CdxButton, CdxIcon, CdxTextArea } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';
import { onMounted, ref } from 'vue';
import { useSchemaStore } from '@/stores/SchemaStore.ts';
import { Schema, SchemaName } from '@neo/domain/Schema.ts';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';
import { SchemaEditorData } from '@/components/SchemaEditor/SchemaEditorDialog.vue';

const props = defineProps<{ schemaName: SchemaName }>();

const schemaStore = useSchemaStore();

const originalSchema = ref<Schema>();
const updatedSchema = ref<Schema>();

onMounted( async (): Promise<void> => {
	originalSchema.value = await schemaStore.getOrFetchSchema( props.schemaName );
} );

// TODO: Move to Schema class
const schemaEquals = ( schemaA: Schema, schemaB: Schema ): boolean => JSON.stringify( schemaA ) === JSON.stringify( schemaB );

const handleSchemaUpdate = ( schemaEditorData: SchemaEditorData ): void => {
	updatedSchema.value = new Schema(
		originalSchema.value!.getName(),
		schemaEditorData.description,
		new PropertyDefinitionList( schemaEditorData.properties )
	);
};

const saveSchema = async (): Promise<void> => {
	if ( updatedSchema.value === undefined ) {
		throw new Error( 'New schema is undefined' );
	}

	// TODO: Saved schema will be broken pending https://github.com/ProfessionalWiki/NeoWiki/issues/345
	await schemaStore.saveSchema( updatedSchema.value );
	console.log( 'Schema saved', updatedSchema.value );
};
</script>
