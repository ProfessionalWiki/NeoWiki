/**
 * Frontend mirror of the backend's Violation wire shape (see
 * src/Domain/Validation/Violation.php). Read from the backend by the
 * persistence layer — either deserialising a 422 response body on save, or
 * the dry-run validate endpoints' 200 body.
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

/**
 * Codes for violations that fire only because a field (or the subject label)
 * has not been filled in yet. They are the only violations that can occur on a
 * field the user has not touched.
 */
const MISSING_VALUE_CODES: ReadonlySet<string> = new Set( [ 'required', 'label-required' ] );

/**
 * Withholds "you have not filled this in yet" violations from the live dry-run
 * while *creating* a subject: every field starts empty and the user is on their
 * way to filling them in, so flagging them mid-creation nags about a mistake
 * not yet made. Editing an existing subject surfaces them normally — there an
 * empty required field is a real gap, not a field still being filled in. Every
 * other violation needs a value to occur, so the field was necessarily touched;
 * those always show live.
 */
export function withoutMissingValueViolations(
	violations: readonly SubjectViolation[],
): SubjectViolation[] {
	return violations.filter( ( v ) => !MISSING_VALUE_CODES.has( v.code ) );
}
