import { isSeverity } from '@/domain/Severity';
import type { SubjectViolation } from '@/domain/SubjectViolation';

export function isShapedAsViolation( raw: unknown ): raw is SubjectViolation {
	if ( typeof raw !== 'object' || raw === null ) {
		return false;
	}
	const v = raw as Record<string, unknown>;

	const propertyNameOk = v.propertyName === null || typeof v.propertyName === 'string';
	const codeOk = typeof v.code === 'string' && v.code.length > 0;
	const argsOk = v.args === undefined || Array.isArray( v.args );
	const severityOk = v.severity === undefined || isSeverity( v.severity );
	const indexOk = v.valuePartIndex === undefined ||
		v.valuePartIndex === null ||
		typeof v.valuePartIndex === 'number';

	return propertyNameOk && codeOk && argsOk && severityOk && indexOk;
}

export function parseViolations( body: unknown ): SubjectViolation[] | null {
	if ( typeof body !== 'object' || body === null ) {
		return null;
	}
	const violations = ( body as { violations?: unknown } ).violations;
	if ( !Array.isArray( violations ) ) {
		return null;
	}
	if ( !violations.every( isShapedAsViolation ) ) {
		return null;
	}
	return violations.map( ( v ) => ( {
		propertyName: v.propertyName,
		code: v.code,
		args: v.args ?? [],
		// Warning is the wire default (ADR 26): a legacy body without the field
		// must render as advisory, not blocking.
		severity: v.severity ?? 'warning',
		valuePartIndex: v.valuePartIndex ?? null,
	} ) );
}
