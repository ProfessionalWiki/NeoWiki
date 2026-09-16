/**
 * A reason a save must not proceed, attributable to one property: the property, and
 * the message to show. Either a field holding text that cannot go into what it edits,
 * or a property definition the wiki will refuse to store.
 */
export interface SaveBlocker {
	propertyName: string;
	message: string;
}
