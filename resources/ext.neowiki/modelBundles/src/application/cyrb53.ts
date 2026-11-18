/* eslint-disable no-bitwise */

/**
 * cyrb53 by bryc (public domain), a 53-bit hash of a string, as hex. Written out rather than taken from
 * SubtleCrypto, which browsers offer only on HTTPS pages: an install record has to hash alike on every wiki.
 */
export function cyrb53( text: string ): string {
	let h1 = 0xdeadbeef;
	let h2 = 0x41c6ce57;

	for ( let i = 0; i < text.length; i++ ) {
		const code = text.charCodeAt( i );
		h1 = Math.imul( h1 ^ code, 2654435761 );
		h2 = Math.imul( h2 ^ code, 1597334677 );
	}

	h1 = Math.imul( h1 ^ ( h1 >>> 16 ), 2246822507 ) ^ Math.imul( h2 ^ ( h2 >>> 13 ), 3266489909 );
	h2 = Math.imul( h2 ^ ( h2 >>> 16 ), 2246822507 ) ^ Math.imul( h1 ^ ( h1 >>> 13 ), 3266489909 );

	return ( 4294967296 * ( 2097151 & h2 ) + ( h1 >>> 0 ) ).toString( 16 ).padStart( 14, '0' );
}
