import type { Value } from '@/domain/Value';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { SubjectViolation } from '@/domain/SubjectViolation';

export interface ValueInputProps<T extends PropertyDefinition> {
	modelValue: Value | undefined;
	label?: string;
	property: T;
	/**
	 * Server-sourced violations for this field, pre-filtered by the parent
	 * to ones whose propertyName matches this property. Absent means no
	 * backend-sourced errors to render.
	 */
	serverViolations?: readonly SubjectViolation[];
}

export type ValueInputEmits = {
	'update:modelValue': [ Value | undefined ];
	/**
	 * Emitted when the user edits a field that had a backend violation, so
	 * the parent can drop the matching serverViolations entry and the red
	 * border clears before the next save.
	 */
	'clear-server-violation': [ { propertyName: string; valuePartIndex: number | null } ];
};

export interface ValueInputExposes {
	getCurrentValue(): Value | undefined;
	/**
	 * Whether the widget is showing the user text it cannot turn into a Value.
	 * A native number input does this: text like "5foo" stays on screen while
	 * the value reads as empty, so getCurrentValue() has nothing to return.
	 * Saving is held while any input reports true, rather than dropping the
	 * text the user can still see. Inputs whose widget cannot reach such a
	 * state omit this.
	 */
	hasUnparseableInput?(): boolean;
}

export type ValueInputEmitFunction = {
	( event: 'update:modelValue', value: Value | undefined ): void;
	( event: 'clear-server-violation', payload: { propertyName: string; valuePartIndex: number | null } ): void;
};
