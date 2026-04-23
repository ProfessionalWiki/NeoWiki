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
