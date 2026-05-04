import type { Constraint } from '@/domain/Constraint';
import { isValueEmpty, type StringValue, type Value, ValueType } from '@/domain/Value';
import type { ValueValidationError } from '@/domain/PropertyType';

export function interpretConstraints(
	constraints: Constraint[],
	value: Value | undefined,
): ValueValidationError[] {
	const errors: ValueValidationError[] = [];
	for ( const constraint of constraints ) {
		errors.push( ...evaluate( constraint, value ) );
	}
	return errors;
}

function evaluate( constraint: Constraint, value: Value | undefined ): ValueValidationError[] {
	switch ( constraint.kind ) {
		case 'required':
			return isValueEmpty( value ) ? [ emit( 'required', constraint ) ] : [];

		case 'minLength': {
			if ( value?.type !== ValueType.String ) return [];
			const out: ValueValidationError[] = [];
			for ( const part of ( value as StringValue ).parts ) {
				if ( part.trim().length < constraint.value ) {
					out.push( emit( 'min-length', constraint, { args: [ constraint.value ], source: part } ) );
				}
			}
			return out;
		}

		case 'maxLength': {
			if ( value?.type !== ValueType.String ) return [];
			const out: ValueValidationError[] = [];
			for ( const part of ( value as StringValue ).parts ) {
				if ( part.trim().length > constraint.value ) {
					out.push( emit( 'max-length', constraint, { args: [ constraint.value ], source: part } ) );
				}
			}
			return out;
		}

		case 'uniqueItems': {
			if ( value?.type !== ValueType.String ) return [];
			const parts = ( value as StringValue ).parts;
			return new Set( parts ).size !== parts.length ? [ emit( 'unique', constraint ) ] : [];
		}

		case 'cardinality': {
			if ( value?.type !== ValueType.String ) return [];
			const parts = ( value as StringValue ).parts;
			return parts.length > constraint.maxItems ? [ emit( 'single-value-only', constraint ) ] : [];
		}

		case 'enum': {
			if ( value?.type !== ValueType.String ) return [];
			const allowed = new Set( constraint.allowedValues );
			const out: ValueValidationError[] = [];
			for ( const part of ( value as StringValue ).parts ) {
				if ( !allowed.has( part ) ) {
					out.push( emit( 'invalid-option', constraint, { args: [ part ], source: part } ) );
				}
			}
			return out;
		}
	}
}

function emit(
	code: string,
	constraint: Constraint,
	extra: Partial<Omit<ValueValidationError, 'code' | 'severity'>> = {},
): ValueValidationError {
	const error: ValueValidationError = { code, ...extra };
	if ( constraint.severity !== undefined ) {
		error.severity = constraint.severity;
	}
	return error;
}
