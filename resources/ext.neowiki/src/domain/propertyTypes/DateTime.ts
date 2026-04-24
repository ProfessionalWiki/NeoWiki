import type { PropertyDefinition } from '@/domain/PropertyDefinition';
import { PropertyName } from '@/domain/PropertyDefinition';
import { newStringValue, type StringValue, ValueType } from '@/domain/Value';
import { BasePropertyType, ValueValidationError } from '@/domain/PropertyType';

export interface DateTimeProperty extends PropertyDefinition {

	/**
	 * Inclusive lower bound. Must be a strict ISO 8601 / xsd:dateTime string
	 * with an explicit timezone offset (e.g. `2025-06-15T12:00:00Z` or
	 * `2025-06-15T12:00:00+02:00`).
	 */
	readonly minimum?: string;

	/**
	 * Inclusive upper bound. Same shape rules as the minimum.
	 */
	readonly maximum?: string;

}

/**
 * Matches xsd:dateTime-like strings with an explicit timezone offset or `Z`.
 * A subsequent calendar-overflow check is used to reject inputs like
 * `2025-02-30T00:00:00Z` that the regex alone cannot detect.
 */
// The only quantifier `\d{1,9}` is bounded and followed by a distinct
// character class, so this is not subject to catastrophic backtracking.
// eslint-disable-next-line security/detect-unsafe-regex
const ISO_DATE_TIME_REGEX = /^(-?\d{4})-(0[1-9]|1[0-2])-(0[1-9]|[12]\d|3[01])T([01]\d|2[0-3]):([0-5]\d):([0-5]\d)(?:\.\d{1,9})?(?<offset>Z|[+-](?:[01]\d|2[0-3]):[0-5]\d)$/;

/**
 * Parses a strict ISO 8601 / xsd:dateTime string with an explicit offset or `Z`.
 * Returns a millisecond timestamp, or `null` if the value is malformed, missing
 * an offset, or a calendar overflow (e.g. Feb 30) that `Date` would silently
 * roll over.
 */
export function parseStrictDateTime( value: string ): number | null {
	const match = ISO_DATE_TIME_REGEX.exec( value );
	if ( match === null || match.groups === undefined ) {
		return null;
	}

	const timestamp = Date.parse( value );
	if ( isNaN( timestamp ) ) {
		return null;
	}

	// Reject calendar overflows (e.g. Feb 30) that Date silently rolls over.
	// Compare the declared year/month/day against the shifted-to-local date
	// using UTC getters, so the check is independent of the host timezone.
	const offsetSegment = match.groups.offset;
	const offsetMinutes = offsetSegment === 'Z' ? 0 : isoOffsetToMinutes( offsetSegment );
	const local = new Date( timestamp + offsetMinutes * 60_000 );

	if (
		local.getUTCFullYear() !== Number( match[ 1 ] ) ||
		local.getUTCMonth() + 1 !== Number( match[ 2 ] ) ||
		local.getUTCDate() !== Number( match[ 3 ] )
	) {
		return null;
	}

	return timestamp;
}

function isoOffsetToMinutes( offset: string ): number {
	const sign = offset.startsWith( '-' ) ? -1 : 1;
	const [ hours, minutes ] = offset.slice( 1 ).split( ':' ).map( Number );
	return sign * ( hours * 60 + minutes );
}

/**
 * Property type for xsd:dateTime-style timestamps.
 *
 * Values must be strict ISO 8601 strings with an explicit timezone offset or
 * `Z` (e.g. `2025-06-15T12:00:00Z`, `2025-06-15T12:00:00+02:00`). Partial
 * values such as year-only (`2025`), year-month (`2025-06`), or date-only
 * (`2025-06-15`), as well as calendar overflows like `2025-02-30T00:00:00Z`,
 * are rejected. The `minimum` and `maximum` bounds are inclusive and must
 * themselves be well-formed ISO 8601 strings.
 *
 * Deliberately not accepted: lowercase `t` separator, colonless offsets
 * (`+0200`), leap seconds (`23:59:60`), `24:00` end-of-day, and expanded
 * `+YYYYY` years — narrower than xsd:dateTime but sufficient for the
 * property-bound use case.
 *
 * If `minimum` or `maximum` on the passed-in property is itself malformed,
 * that bound is silently ignored during validation (fail-open). The PHP
 * persistence layer rejects malformed bounds at construction, so this only
 * matters if something bypasses that path.
 */
export class DateTimeType extends BasePropertyType<DateTimeProperty, StringValue> {

	public static readonly valueType = ValueType.String;

	public static readonly typeName = 'dateTime';

	public getDisplayAttributeNames(): string[] {
		return [];
	}

	public getExampleValue(): StringValue {
		return newStringValue( '2026-01-01T12:00:00Z' );
	}

	public createPropertyDefinitionFromJson( base: PropertyDefinition, json: any ): DateTimeProperty {
		return {
			...base,
			minimum: json.minimum,
			maximum: json.maximum,
		} as DateTimeProperty;
	}

	public validate( value: StringValue | undefined, property: DateTimeProperty ): ValueValidationError[] {
		const errors: ValueValidationError[] = [];

		if ( property.required && value === undefined ) {
			errors.push( { code: 'required' } );
			return errors;
		}

		if ( value !== undefined && value.parts.length > 0 ) {
			const timestamp = parseStrictDateTime( value.parts[ 0 ] );

			if ( timestamp === null ) {
				errors.push( { code: 'invalid-datetime' } );
				return errors;
			}

			const minimum = property.minimum;
			const minimumTimestamp = minimum !== undefined ? parseStrictDateTime( minimum ) : null;
			if ( minimum !== undefined && minimumTimestamp !== null && timestamp < minimumTimestamp ) {
				errors.push( {
					code: 'min-value',
					args: [ minimum ],
				} );
			}

			const maximum = property.maximum;
			const maximumTimestamp = maximum !== undefined ? parseStrictDateTime( maximum ) : null;
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
 * Formats a UTC ISO 8601 string as a human-readable host-local wall-clock
 * with timezone abbreviation, using the user's browser locale.
 *
 * Falls back to the raw input when the ISO cannot be parsed, so malformed
 * values surface verbatim in the UI rather than as `Invalid Date`.
 */
export function formatDateTimeForDisplay( iso: string ): string {
	const date = new Date( iso );
	if ( isNaN( date.getTime() ) ) {
		return iso;
	}

	// Per-component options rather than dateStyle+timeStyle: ECMA-402 throws
	// when dateStyle/timeStyle is combined with timeZoneName.
	return date.toLocaleString( undefined, {
		year: 'numeric',
		month: 'short',
		day: 'numeric',
		hour: '2-digit',
		minute: '2-digit',
		second: '2-digit',
		timeZoneName: 'short',
	} );
}

type DateTimePropertyAttributes = Omit<Partial<DateTimeProperty>, 'name'> & {
	name?: string | PropertyName;
};

export function newDateTimeProperty( attributes: DateTimePropertyAttributes = {} ): DateTimeProperty {
	return {
		name: attributes.name instanceof PropertyName ? attributes.name : new PropertyName( attributes.name || 'DateTime' ),
		type: DateTimeType.typeName,
		description: attributes.description ?? '',
		required: attributes.required ?? false,
		default: attributes.default,
		minimum: attributes.minimum,
		maximum: attributes.maximum,
	};
}
