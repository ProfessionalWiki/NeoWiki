import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType, ValueValidationError } from '@/domain/PropertyType';

export interface DateProperty extends PropertyDefinition {

	/**
	 * Inclusive lower bound. Must be a strict ISO 8601 calendar date in
	 * `YYYY-MM-DD` form with no time or timezone component (e.g. `2025-06-15`).
	 */
	readonly minimum?: string;

	/**
	 * Inclusive upper bound. Same shape rules as the minimum.
	 */
	readonly maximum?: string;

}

/**
 * Matches xsd:date-like strings: a calendar date with no time or timezone
 * component. A subsequent calendar-overflow check is used to reject inputs
 * like `2025-02-30` that the regex alone cannot detect.
 */
const ISO_DATE_REGEX = /^(-?\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])$/;

/**
 * Parses a strict ISO 8601 calendar date (`YYYY-MM-DD`). Returns a millisecond
 * timestamp at UTC midnight, or `null` if the value is malformed, carries a
 * time/timezone component, or is a calendar overflow (e.g. Feb 30) that `Date`
 * would silently roll over.
 *
 * The timestamp is only used for chronological ordering against the min/max
 * bounds; it is anchored to UTC so the comparison is independent of the host
 * timezone.
 */
export function parseStrictDate( value: string ): number | null {
	const match = ISO_DATE_REGEX.exec( value );
	if ( match === null ) {
		return null;
	}

	const timestamp = Date.parse( `${ value }T00:00:00Z` );
	if ( isNaN( timestamp ) ) {
		return null;
	}

	// Reject calendar overflows (e.g. Feb 30) that Date silently rolls over.
	// Compare the declared year/month/day against the parsed UTC date.
	const utc = new Date( timestamp );
	if (
		utc.getUTCFullYear() !== Number( match[ 1 ] ) ||
		utc.getUTCMonth() + 1 !== Number( match[ 2 ] ) ||
		utc.getUTCDate() !== Number( match[ 3 ] )
	) {
		return null;
	}

	return timestamp;
}

/**
 * Property type for xsd:date-style calendar dates without a time component.
 *
 * Values must be strict ISO 8601 dates in `YYYY-MM-DD` form (e.g.
 * `2025-06-15`). Values carrying a time or timezone component
 * (`2025-06-15T00:00:00Z`), partial values such as year-only (`2025`) or
 * year-month (`2025-06`), and calendar overflows like `2025-02-30` are
 * rejected. The `minimum` and `maximum` bounds are inclusive and must
 * themselves be well-formed ISO 8601 dates.
 *
 * If `minimum` or `maximum` on the passed-in property is itself malformed,
 * that bound is silently ignored during validation (fail-open). The PHP
 * persistence layer rejects malformed bounds at construction, so this only
 * matters if something bypasses that path.
 */
export class DateType extends BasePropertyType<DateProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'date';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): StringValue {
		return newStringValue( '2026-01-01' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): DateProperty {
		return {
			...base,
			minimum: json.minimum ?? undefined,
			maximum: json.maximum ?? undefined,
		} as DateProperty;
	}

	public validate( value: StringValue | undefined, property: DateProperty ): ValueValidationError[] {
		const errors: ValueValidationError[] = [];

		if ( property.required && value === undefined ) {
			errors.push( { code: 'required' } );
			return errors;
		}

		if ( value !== undefined && value.parts.length > 0 ) {
			const timestamp = parseStrictDate( value.parts[ 0 ] );

			if ( timestamp === null ) {
				errors.push( { code: 'invalid-date' } );
				return errors;
			}

			const minimum = property.minimum;
			const minimumTimestamp = minimum !== undefined ? parseStrictDate( minimum ) : null;
			if ( minimum !== undefined && minimumTimestamp !== null && timestamp < minimumTimestamp ) {
				errors.push( {
					code: 'min-value',
					args: [ minimum ],
				} );
			}

			const maximum = property.maximum;
			const maximumTimestamp = maximum !== undefined ? parseStrictDate( maximum ) : null;
			if ( maximum !== undefined && maximumTimestamp !== null && timestamp > maximumTimestamp ) {
				errors.push( {
					code: 'max-value',
					args: [ maximum ],
				} );
			}
		}

		return errors;
	}

}

/**
 * Formats a `YYYY-MM-DD` string as a human-readable date using the user's
 * browser locale, with no time component.
 *
 * The date is interpreted in UTC and rendered with `timeZone: 'UTC'` so the
 * displayed calendar day always matches the stored day regardless of the host
 * timezone. Falls back to the raw input when it cannot be parsed, so malformed
 * values surface verbatim in the UI rather than as `Invalid Date`.
 */
export function formatDateForDisplay( iso: string ): string {
	if ( parseStrictDate( iso ) === null ) {
		return iso;
	}

	const date = new Date( `${ iso }T00:00:00Z` );

	return date.toLocaleDateString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		timeZone: 'UTC',
	} );
}

type DatePropertyAttributes = Omit<Partial<DateProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newDateProperty( attributes: DatePropertyAttributes = {} ): DateProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'Date' ),
		type: DateType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		minimum: attributes.minimum,
		maximum: attributes.maximum,
	};
}
