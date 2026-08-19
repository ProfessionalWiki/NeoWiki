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
	 * The message the widget is showing because it holds text it cannot turn
	 * into a Value, or null when it holds none. A native number input reaches
	 * this state: text like "5foo" stays on screen while the value reads as
	 * empty, so getCurrentValue() has nothing to return. Callers hold the save
	 * while any input reports a message, and surface that same message, rather
	 * than dropping text the user can still see. Inputs whose widget cannot
	 * reach such a state omit this.
	 */
	unparseableInputMessage?(): string | null;
}

export type ValueInputEmitFunction = {
	( event: 'update:modelValue', value: Value | undefined ): void;
	( event: 'clear-server-violation', payload: { propertyName: string; valuePartIndex: number | null } ): void;
};
