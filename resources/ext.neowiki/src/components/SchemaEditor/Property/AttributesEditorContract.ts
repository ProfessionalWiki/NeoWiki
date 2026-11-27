import type { PropertyDefinition } from '@/domain/PropertyDefinition';

export interface AttributesEditorProps<T extends PropertyDefinition> {
	property: T;
}

export type AttributesEditorEmits<T extends PropertyDefinition> = {
	'update:property': [Partial<T>];
};

export interface AttributesEditorExposes {
	/**
	 * A message an attribute field is showing that holds the save, such as for text the field
	 * cannot turn into an attribute, or null. Callers hold the save while it is non-null, as for a
	 * value input's `unparseableInputMessage`. Editors whose fields show no such message omit this.
	 */
	unparseableInputMessage?(): string | null;
}
