import { isSeverity, type Severity } from '@/domain/Severity';

/**
 * Parses and serializes the two Schema-JSON forms a Constraint can take (ADR 26):
 * a bare scalar/array (default `warning` severity) or an object `{ value, severity }`
 * (booleans drop `value` and imply `true`, except `multiple`, whose object form carries
 * `"value": false`; `options` carries the array in `value`).
 * Mirror of the backend's SeverityNormalizer (src/Domain/Validation/SeverityNormalizer.php).
 *
 * A value is treated as object-form iff it is an object carrying a `severity` key.
 * This is intentionally permissive: being type-agnostic is what lets a custom
 * Property Type's own Constraint keys round-trip without any core change.
 */

/** Core keys handled outside the Constraint model; never severity-bearing. */
const RESERVED = [ 'type', 'description', 'default' ];

/**
 * The core boolean Constraints, whose object form carries no `value` key (see
 * docs/api/schema-format.md): `false` is representable only as the bare scalar.
 */
const VALUE_LESS_BOOLEAN_CONSTRAINTS = [ 'required', 'uniqueItems' ];

/**
 * The boolean Constraints whose rule applies while they are `false`: `multiple: false`
 * is what makes a property single-valued. `true` therefore switches the rule off rather
 * than on, leaving any severity annotating nothing.
 */
const CONSTRAINTS_ACTIVE_WHEN_FALSE = [ 'multiple' ];

/**
 * Returns the property JSON with object-form Constraints unwrapped to their
 * bare values, together with the name-to-severity map extracted from them.
 */
export function extractSeverities(
	property: Record<string, unknown>,
): [ Record<string, unknown>, Record<string, Severity> ] {
	const values: Record<string, unknown> = { ...property };
	const severities: Record<string, Severity> = {};

	for ( const [ key, raw ] of Object.entries( property ) ) {
		if ( RESERVED.includes( key ) ) {
			continue;
		}
		if ( typeof raw !== 'object' || raw === null || !( 'severity' in raw ) ) {
			continue;
		}

		const { severity } = raw as { severity: unknown };
		if ( !isSeverity( severity ) ) {
			throw new Error( 'Invalid severity: ' + JSON.stringify( severity ) );
		}

		// 'value' in raw, not ??: an explicit "value": null must stay null (the
		// boolean object form is the one that legitimately omits the key). Only the
		// custom keys of unregistered types actually round-trip such a null — the
		// registered type parsers normalize null bounds to undefined, dropping the
		// key and its annotation on re-save.
		values[ key ] = 'value' in raw ? ( raw as { value: unknown } ).value : true;
		severities[ key ] = severity;
	}

	return [ values, severities ];
}

/**
 * Canonical output: re-wrap only `error`-annotated (present) keys; `warning` is the
 * default and emits as shorthand, so unannotated Schemas round-trip byte-for-byte.
 */
export function applySeverities(
	json: Record<string, unknown>,
	severities: Readonly<Record<string, Severity>>,
): Record<string, unknown> {
	const result = { ...json };

	for ( const [ key, severity ] of Object.entries( severities ) ) {
		if ( severity === 'warning' || result[ key ] === undefined ) {
			continue;
		}

		// Unchecking an annotated required/uniqueItems in the schema editor leaves
		// the severity entry behind on a now-false value. Their object form cannot
		// carry false, so drop the annotation rather than emit a form the backend
		// rejects. The PHP mirror has no such branch: only the editor can create
		// this state, since authoring-time validation forbids it in stored JSON.
		if ( result[ key ] === false && VALUE_LESS_BOOLEAN_CONSTRAINTS.includes( key ) ) {
			continue;
		}

		// The mirror of the branch above for the Constraints that are active when false:
		// the editor keeps the severity of a rule the author switched off, so that it
		// survives switching it back on while the dialog is open. Emitting it would fail
		// the save: schemaContentSchema.json pins `multiple`'s object form to `false`.
		if ( result[ key ] === true && CONSTRAINTS_ACTIVE_WHEN_FALSE.includes( key ) ) {
			continue;
		}

		// Only `true` may drop the value key: extractSeverities reads the value-less
		// form as true, so emitting it for `false` would flip a Constraint the author
		// disabled.
		result[ key ] = result[ key ] === true ?
			{ severity } :
			{ value: result[ key ], severity };
	}

	return result;
}
