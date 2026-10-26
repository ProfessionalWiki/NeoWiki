import type { PropertyDefinition } from '@/domain/PropertyDefinition';

export interface AttributesEditorProps<T extends PropertyDefinition> {
	property: T;
}

export type AttributesEditorEmits<T extends PropertyDefinition> = {
	'update:property': [Partial<T>];
};

export interface AttributesEditorExposes {
	/**
	 * The message a field of this editor is showing because it holds text it cannot turn into
	 * an attribute, or null when none does, as ValueInputExposes.unparseableInputMessage
	 * describes for a value. Editors without such a field omit this.
	 */
	unparseableInputMessage?(): string | null;
}
