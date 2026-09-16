/**
 * A field showing the user text that cannot go into what it edits, such as text
 * its widget cannot turn into a Value: the property it edits, and the message the
 * field is already showing.
 */
export interface UnparseableInput {
	propertyName: string;
	message: string;
}
