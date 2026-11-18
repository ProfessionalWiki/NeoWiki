/**
 * A reason a save must not proceed: the property it is in, absent for a field of the Schema
 * as a whole, and the message to show. Either a field holding text that cannot go into what
 * it edits, whose message that field is already showing, or a property definition the wiki
 * will refuse to store, whose message is derived from the Schema and shown nowhere else — and
 * which may name a property the user has not selected, so a notification is the only signal.
 */
export interface SaveBlocker {
	propertyName?: string;
	message: string;
}
