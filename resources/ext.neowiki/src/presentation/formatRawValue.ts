/**
 * Renders the raw stored data of an unregistered-type Value as text, so it stays
 * visible to readers even though its property type cannot be interpreted.
 */
export function formatRawValue( raw: unknown ): string {
	if ( raw === null || raw === undefined ) {
		return '';
	}

	if ( typeof raw === 'string' ) {
		return raw;
	}

	if ( typeof raw === 'number' || typeof raw === 'boolean' ) {
		return String( raw );
	}

	return JSON.stringify( raw );
}
