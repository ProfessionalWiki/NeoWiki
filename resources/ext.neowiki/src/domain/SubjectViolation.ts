import type { Severity } from '@/domain/Severity';

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
 *
 * severity decides styling and blocking (ADR 26): an `error` can be rejected
 * under enforcement, a `warning` never blocks and renders as advisory.
 */
export interface SubjectViolation {
	readonly propertyName: string | null;
	readonly code: string;
	readonly args: readonly unknown[];
	readonly severity: Severity;
	readonly valuePartIndex: number | null;
}

/**
 * Codes for violations that fire only because a field has not been filled in yet. They are the
 * only violations that can occur on a field the user has not touched.
 */
const MISSING_VALUE_CODES: ReadonlySet<string> = new Set( [ 'required' ] );

/** Raised when a relation names a Subject the server cannot resolve; its first arg is that id. */
const RELATION_TARGET_NOT_FOUND = 'relation-target-not-found';

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

/**
 * The server reports a relation target it cannot resolve, which is right for a target nobody has
 * created and wrong for one this editing session is about to create: it is in front of the user,
 * in the pane beside the field. Withheld for those, by the id the violation names, so the editor
 * does not tell the user that the Subject they just made is missing.
 */
export function withoutUnsavedTargetViolations(
	violations: readonly SubjectViolation[],
	unsavedTargetIds: readonly string[],
): SubjectViolation[] {
	return violations.filter( ( v ) => !(
		v.code === RELATION_TARGET_NOT_FOUND &&
		typeof v.args[ 0 ] === 'string' &&
		unsavedTargetIds.includes( v.args[ 0 ] )
	) );
}
