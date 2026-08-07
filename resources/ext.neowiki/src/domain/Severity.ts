/**
 * Validation severity of a Constraint or a violation (ADR 26). Mirror of the
 * backend's Severity enum (src/Domain/Validation/Severity.php). `warning` is
 * the default everywhere a severity can be omitted.
 */
export type Severity = 'error' | 'warning';

export function isSeverity( value: unknown ): value is Severity {
	return value === 'error' || value === 'warning';
}
