import type { PropertyDefinition } from '@/domain/PropertyDefinition';

export interface AttributesEditorProps<T extends PropertyDefinition> {
	property: T;
}

export type AttributesEditorEmits<T extends PropertyDefinition> = {
	'update:property': [Partial<T>];
};

export interface AttributesEditorExposes {
	/**
	 * The message an attribute field is showing because the definition cannot take what it
	 * holds, or null. Callers hold the save while it is non-null, as for a value input's
	 * `unparseableInputMessage`. Editors whose fields cannot reach such a state omit this.
	 */
	unparseableInputMessage?(): string | null;
}
