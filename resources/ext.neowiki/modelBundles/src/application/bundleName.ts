/**
 * A Model bundle is identified by its name, which titles its install record page: spaces and underscores
 * are one, as in a page title.
 */
export function normalizeBundleName( name: string ): string {
	return name.normalize( 'NFC' ).replace( /[\s_]+/g, ' ' ).trim();
}

export function sameBundleName( a: string, b: string ): boolean {
	return normalizeBundleName( a ) === normalizeBundleName( b );
}

/**
 * What MediaWiki refuses in a page title, plus the `/` that would put the record on a subpage of another.
 */
const UNUSABLE = /[#<>[\]|{}/\ufffd]|%[0-9A-Fa-f]{2}|&[A-Za-z0-9\u0080-\uffff]+;|~~~/;

/**
 * Of the 255 bytes MediaWiki allows a page title, what `NeoWiki/Model_bundles/<name>.json` leaves the name.
 */
const MAX_BYTES = 255 - 'NeoWiki/Model_bundles/.json'.length;

export function isUsableBundleName( name: string ): boolean {
	const normalized = normalizeBundleName( name );
	return normalized !== '' && new TextEncoder().encode( normalized ).length <= MAX_BYTES && !UNUSABLE.test( normalized ) &&
		![ ...normalized ].some( ( character ) => character.charCodeAt( 0 ) < 0x20 || character.charCodeAt( 0 ) === 0x7f );
}
