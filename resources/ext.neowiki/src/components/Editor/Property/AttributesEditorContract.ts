import type { PropertyDefinition } from '@neo/domain/PropertyDefinition';

export interface AttributesEditorProps<T extends PropertyDefinition> {
	property: T;
}

export type AttributesEditorEmits<T extends PropertyDefinition> = {
	'update:property': [Partial<T>];
};
