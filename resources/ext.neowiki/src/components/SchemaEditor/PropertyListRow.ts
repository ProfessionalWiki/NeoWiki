/** A row of the property list: the Schema's own, whose pane holds the Schema's fields, the Label row, or a property's. */
export type PropertyListRow = { kind: 'schema' } | { kind: 'subjectLabel' } | { kind: 'property'; name: string };

export const SCHEMA_ROW: PropertyListRow = { kind: 'schema' };

export const LABEL_ROW: PropertyListRow = { kind: 'subjectLabel' };

export function propertyRow( name: string ): PropertyListRow {
	return { kind: 'property', name };
}
