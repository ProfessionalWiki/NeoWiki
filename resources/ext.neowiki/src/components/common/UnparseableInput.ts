/**
 * A field showing the user text its widget cannot turn into a Value: the
 * property it edits, and the message the field is already showing.
 */
export interface UnparseableInput {
	propertyName: string;
	message: string;
}
