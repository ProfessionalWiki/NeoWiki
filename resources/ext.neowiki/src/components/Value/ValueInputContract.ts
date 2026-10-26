import type { InjectionKey } from 'vue';
import type { Value } from '@/domain/Value';
import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import type { SubjectViolation } from '@/domain/SubjectViolation';
import type { SubjectId } from '@/domain/SubjectId';

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
	/**
	 * Not emitted while the widget holds text it cannot turn into a Value, such as "-" in a
	 * number field: reporting it as empty would erase the stored value.
	 */
	'update:modelValue': [ Value | undefined ];
	/**
	 * Emitted when the user edits a field that had a backend violation, so
	 * the parent can drop the matching serverViolations entry and the red
	 * border clears before the next save.
	 */
	'clear-server-violation': [ { propertyName: string; valuePartIndex: number | null } ];
	/**
	 * Emitted only by relation inputs, and only where the host provides
	 * RelationTargetEditingKey.
	 */
	'edit-relation-target': [ SubjectId ];
};

export interface ValueInputExposes {
	getCurrentValue(): Value | undefined;
	/**
	 * The message the widget is showing because it holds text it cannot turn
	 * into a Value, or null when it holds none. Native number and date inputs
	 * reach this state: text like "5foo", or a date with a part cleared, stays
	 * on screen while getCurrentValue() returns the last value the widget could
	 * read. Callers hold the save while any input reports a message, and surface
	 * that same message, rather than saving a value the field does not show.
	 * Inputs whose widget cannot reach such a state omit this.
	 */
	unparseableInputMessage?(): string | null;
}

export type ValueInputEmitFunction = {
	( event: 'update:modelValue', value: Value | undefined ): void;
	( event: 'clear-server-violation', payload: { propertyName: string; valuePartIndex: number | null } ): void;
};

/**
 * Provided as true by hosts that can open a relation target for in-place editing.
 * RelationInput then renders a per-target edit affordance.
 */
export const RelationTargetEditingKey: InjectionKey<boolean> = Symbol( 'NeoWikiRelationTargetEditing' );
