import { PropertyDefinition } from '@neo/domain/PropertyDefinition.ts';

export type SchemaEditorData = {
	description: string;
	properties: PropertyDefinition[];
};
