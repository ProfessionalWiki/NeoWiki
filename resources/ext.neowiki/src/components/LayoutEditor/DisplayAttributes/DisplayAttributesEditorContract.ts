import type { PropertyDefinition } from '@/domain/PropertyDefinition';

/**
 * An editor gets a new instance for each property it is opened for, so it may take what its fields start with from
 * its props once.
 */
export interface DisplayAttributesEditorProps<
	T extends PropertyDefinition,
	A extends Record<string, unknown> = Record<string, unknown>,
> {
	/** The property as its Schema defines it: its Display Attributes apply where the Layout sets none. */
	property: T;
	/** The Display Attributes the Layout sets for the property. */
	displayAttributes: A;
}

export type DisplayAttributesEditorEmits<A extends Record<string, unknown> = Record<string, unknown>> = {
	/**
	 * The Display Attributes to change: a value sets one for the Layout, undefined drops it so that the Schema's
	 * applies, and one left out stays as it is.
	 */
	'update:displayAttributes': [ Partial<A> ];
};
