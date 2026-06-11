/**
 * Frontend mirror of the backend's Violation wire shape (see
 * src/Domain/Validation/Violation.php). Read from the backend by the
 * persistence layer — either deserialising a 422 response body on save, or
 * the dry-run validate endpoints' 200 body. The live per-input
 * ValueValidationError type covers client-side validation.
 *
 * propertyName === null is used for subject-level violations such as
 * 'schema-not-found' that don't anchor to a specific field.
 *
 * valuePartIndex === null is used for subject-level violations and for
 * single-value properties. For per-value violations on multi-value
 * properties (e.g. one bad URL among many), it identifies which entry
 * in the multi-input is at fault.
 */
export interface SubjectViolation {
	readonly propertyName: string | null;
	readonly code: string;
	readonly args: readonly unknown[];
	readonly valuePartIndex: number | null;
}
