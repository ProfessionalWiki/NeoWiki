import { Value } from '@/domain/Value';
import { PropertyType, ValueValidationError } from '@/domain/PropertyType';
import { PropertyDefinition } from '@/domain/PropertyDefinition';
import { ValidationMessages } from '@wikimedia/codex';

/**
 * Validation codes the server is authoritative for. They are not surfaced in
 * live client-side validation, so a value field is not flagged before the user
 * has entered a value; the server still returns and enforces them at save time
 * (and via its dry-run). This is the single source of truth for that policy:
 * every value input runs its live validation through liveValidationErrors() or
 * liveValidationMessages() rather than filtering codes itself.
 */
export const SERVER_ENFORCED_CODES: readonly string[] = [ 'required' ];

/**
 * The errors a value input should surface live: the full client-side
 * validation minus the codes the server is authoritative for.
 */
export function liveValidationErrors(
	value: Value | undefined,
	propertyType: PropertyType,
	property: PropertyDefinition,
): ValueValidationError[] {
	return propertyType.validate( value, property )
		.filter( ( error ) => !SERVER_ENFORCED_CODES.includes( error.code ) );
}

function firstErrorMessage( errors: ValueValidationError[] ): ValidationMessages {
	const error = errors[ 0 ];
	if ( error ) {
		return {
			error: mw.message(
				`neowiki-field-${ error.code }`,
				...( error.args ?? [] ),
			).text(),
		};
	}

	return {};
}

/**
 * Format the first validation error, including the server-enforced codes. Value
 * inputs use {@link liveValidationMessages} instead, which omits those codes;
 * this full-validation variant is kept as part of the public API surface.
 */
export function validateValue( value: Value, propertyType: PropertyType, property: PropertyDefinition ): ValidationMessages {
	return firstErrorMessage( propertyType.validate( value, property ) );
}

/**
 * Format the live errors for a value input: the server-enforced codes are not
 * surfaced. See {@link liveValidationErrors}.
 */
export function liveValidationMessages( value: Value, propertyType: PropertyType, property: PropertyDefinition ): ValidationMessages {
	return firstErrorMessage( liveValidationErrors( value, propertyType, property ) );
}
