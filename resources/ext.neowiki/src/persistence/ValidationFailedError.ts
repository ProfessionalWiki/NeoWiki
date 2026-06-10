import type { SubjectViolation } from '@/domain/SubjectViolation';

export class ValidationFailedError extends Error {

	public readonly violations: readonly SubjectViolation[];

	public constructor( violations: readonly SubjectViolation[] ) {
		super( 'Validation failed' );
		this.name = 'ValidationFailedError';
		this.violations = violations;

		// Restore prototype chain — necessary for `instanceof` to work after
		// TypeScript transpiles `extends Error` (see TS handbook on extending
		// built-ins).
		Object.setPrototypeOf( this, ValidationFailedError.prototype );
	}

}
