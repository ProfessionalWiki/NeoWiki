/**
 * Host-timezone-aware conversion between ISO 8601 strings and the
 * `datetime-local` input wire format (`YYYY-MM-DDTHH:mm`).
 *
 * Mirrors `resources/ext.neowiki/src/domain/propertyTypes/dateTimeConversion.ts`
 * in NeoWiki core prior to DateTime being extracted to this extension.
 *
 * - Storage format is always UTC ISO 8601 (e.g. `...Z`).
 * - `datetime-local` inputs operate in the host local timezone.
 * - Inputs with explicit offsets (`+05:00`) are accepted and round-tripped
 *   as the same instant; they are not silently re-interpreted as UTC.
 */

function pad( value ) {
	return String( value ).padStart( 2, '0' );
}

function toLocalInputValue( iso ) {
	if ( iso === undefined || iso === '' ) {
		return '';
	}

	var date = new Date( iso );
	if ( isNaN( date.getTime() ) ) {
		return '';
	}

	return date.getFullYear() + '-' + pad( date.getMonth() + 1 ) + '-' + pad( date.getDate() ) +
		'T' + pad( date.getHours() ) + ':' + pad( date.getMinutes() );
}

function fromLocalInputValue( local ) {
	if ( local === '' ) {
		return undefined;
	}

	var date = new Date( local );
	if ( isNaN( date.getTime() ) ) {
		return undefined;
	}

	return date.toISOString();
}

/**
 * Formats a UTC ISO 8601 string as a human-readable host-local wall-clock
 * with timezone abbreviation, using the user's browser locale.
 *
 * Falls back to the raw input when the ISO cannot be parsed, so malformed
 * values surface verbatim in the UI rather than as `Invalid Date`.
 */
function formatDateTimeForDisplay( iso ) {
	var date = new Date( iso );
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
		timeZoneName: 'short'
	} );
}

module.exports = exports = {
	toLocalInputValue: toLocalInputValue,
	fromLocalInputValue: fromLocalInputValue,
	formatDateTimeForDisplay: formatDateTimeForDisplay
};
