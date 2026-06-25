import type { SubjectViolation } from '@/domain/SubjectViolation';

export function isShapedAsViolation( raw: unknown ): raw is SubjectViolation {
	if ( typeof raw !== 'object' || raw === null ) {
		return false;
	}
	const v = raw as Record<string, unknown>;

	const propertyNameOk = v.propertyName === null || typeof v.propertyName === 'string';
	const codeOk = typeof v.code === 'string' && v.code.length > 0;
	const argsOk = v.args === undefined || Array.isArray( v.args );
	const indexOk = v.valuePartIndex === undefined ||
		v.valuePartIndex === null ||
		typeof v.valuePartIndex === 'number';

	return propertyNameOk && codeOk && argsOk && indexOk;
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
		valuePartIndex: v.valuePartIndex ?? null,
	} ) );
}
