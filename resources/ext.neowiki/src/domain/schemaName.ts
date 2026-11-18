/**
 * Approximates MediaWiki title normalisation for a Schema name (schemas are
 * wiki pages) so a duplicate-name check resolves to the same page a save would.
 * The save is no guard: it writes to whichever page the name resolves to.
 */
export function normalizeSchemaName( name: string ): string {
	const collapsed = name.normalize( 'NFC' ).split( '#' )[ 0 ]
		.replace( /[\u200E\u200F\u202A-\u202E]/g, '' )
		.replace( /[\s_]+/g, ' ' )
		.trim();
	return collapsed.charAt( 0 ).toUpperCase() + collapsed.slice( 1 );
}
