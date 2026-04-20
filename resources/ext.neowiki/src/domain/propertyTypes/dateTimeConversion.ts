/**
 * Host-timezone-aware conversion between ISO 8601 strings and the
 * `datetime-local` input wire format (`YYYY-MM-DDTHH:mm`).
 *
 * - Storage format is always UTC ISO 8601 (e.g. `...Z`).
 * - `datetime-local` inputs operate in the host local timezone.
 * - Inputs with explicit offsets (`+05:00`) are accepted and round-tripped
 *   as the same instant; they are not silently re-interpreted as UTC.
 */

function pad( value: number ): string {
	return String( value ).padStart( 2, '0' );
}

export function toLocalInputValue( iso: string | undefined ): string {
	if ( iso === undefined || iso === '' ) {
		return '';
	}

	const date = new Date( iso );
	if ( isNaN( date.getTime() ) ) {
		return '';
	}

	return `${ date.getFullYear() }-${ pad( date.getMonth() + 1 ) }-${ pad( date.getDate() ) }` +
		`T${ pad( date.getHours() ) }:${ pad( date.getMinutes() ) }`;
}

export function fromLocalInputValue( local: string ): string | undefined {
	if ( local === '' ) {
		return undefined;
	}

	const date = new Date( local );
	if ( isNaN( date.getTime() ) ) {
		return undefined;
	}

	return date.toISOString();
}
