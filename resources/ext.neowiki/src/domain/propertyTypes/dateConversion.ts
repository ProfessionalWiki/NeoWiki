/**
 * Conversion between stored ISO 8601 date strings and the `<input type="date">`
 * wire format.
 *
 * - Storage format is a calendar date `YYYY-MM-DD` (no time, no timezone).
 * - The `date` input control's value is also `YYYY-MM-DD` (or empty).
 *
 * The two formats coincide, so conversion is an identity guarded by parsing:
 * malformed or non-date values collapse to the empty/undefined sentinel rather
 * than being passed through to the control or persisted.
 */

import { parseStrictDate } from '@/domain/propertyTypes/Date';

export function toDateInputValue( iso: string | undefined ): string {
	if ( iso === undefined || iso === '' ) {
		return '';
	}

	return parseStrictDate( iso ) === null ? '' : iso;
}

export function fromDateInputValue( local: string ): string | undefined {
	if ( local === '' ) {
		return undefined;
	}

	return parseStrictDate( local ) === null ? undefined : local;
}
