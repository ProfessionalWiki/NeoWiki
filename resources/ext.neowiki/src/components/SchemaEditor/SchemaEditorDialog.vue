<template>
	<div>
		<CdxDialog title="TODO">
			<SchemaEditor
				:schema-name="schema.getName()"
				:description="schema.getDescription()"
				:properties="Object.values( schema.getPropertyDefinitions().asRecord() )"
				@update:schema="handleSchemaUpdate"
			/>

			<CdxTextArea />

			<CdxButton
				action="progressive"
				weight="primary"
				:disabled="updatedSchema === undefined || schemaEquals( schema, updatedSchema )"
				@click="saveSchema"
			>
				<CdxIcon :icon="cdxIconCheck" />
				{{ $i18n( 'neowiki-save-schema' ).text() }}
			</CdxButton>
		</CdxDialog>
	</div>
</template>

<script setup lang="ts">
import SchemaEditor from '@/components/SchemaEditor/SchemaEditor.vue';
import { CdxButton, CdxDialog, CdxIcon, CdxTextArea } from '@wikimedia/codex';
import { cdxIconCheck } from '@wikimedia/codex-icons';
import { Schema } from '@neo/domain/Schema.ts';
import { ref } from 'vue';
import { PropertyDefinitionList } from '@neo/domain/PropertyDefinitionList.ts';
import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';

// TODO: Does the dialog need the full schema?
const props = defineProps<{ schema: Schema }>();

const updatedSchema = ref<Schema>();

// TODO: Move to Schema class
const schemaEquals = ( schemaA: Schema, schemaB: Schema ): boolean => JSON.stringify( schemaA ) === JSON.stringify( schemaB );

const handleSchemaUpdate = ( schemaEditorData: SchemaEditorData ): void => {
	updatedSchema.value = new Schema(
		props.schema.getName(),
		schemaEditorData.description,
		new PropertyDefinitionList( schemaEditorData.properties )
	);
};

const saveSchema = async (): Promise<void> => {
	// TODO: emit event
};

export type SchemaEditorData = {
	description: string;
	properties: PropertyDefinition[];
};
</script>
