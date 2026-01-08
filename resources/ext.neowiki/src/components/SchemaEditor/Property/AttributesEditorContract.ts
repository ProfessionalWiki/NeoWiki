import type { PropertyDefinition } from '@/domain/PropertyDefinition';

export interface AttributesEditorProps<T extends PropertyDefinition> {
	property: T;
}

export type AttributesEditorEmits<T extends PropertyDefinition> = {
	'update:property': [Partial<T>];
};
