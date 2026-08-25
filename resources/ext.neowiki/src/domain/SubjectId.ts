/**
 * The local Source's id grammar (ADR 14): `s` plus 14 characters of a 58-character alphabet that omits
 * the visually ambiguous 0, O, I and l.
 */
const LOCAL_ID_PATTERN = /^s[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{14}$/;

/**
 * A Source key is an identifier, not a path: a letter followed by up to 63 letters, digits, underscores
 * or hyphens. The local key is the MediaWiki Wiki ID (ADR 22).
 */
const SOURCE_KEY_PATTERN = /^[A-Za-z][A-Za-z0-9_-]{0,63}$/;

/**
 * A non-local localId is opaque to NeoWiki, so only its serialization is constrained: RFC 3986 pchar,
 * minus percent-encoding and minus the two sub-delimiters an HTML attribute would have to escape (`&`
 * and `'`). Mirrors SubjectId::FOREIGN_LOCAL_ID_PATTERN in PHP.
 */
const FOREIGN_LOCAL_ID_PATTERN = /^[A-Za-z0-9._~:@!$()*+,;=-]{1,256}$/;

/**
 * A Subject's identity: the Source that produced it paired with that Source's own id for it (ADR 23).
 *
 * A local Subject serializes to its bare local id, everything else to `sourceKey:localId`, split at the
 * first colon. Context-free, and that is all the frontend needs: an id reaches it already canonical,
 * because canonicalizing one that names the local Source explicitly needs the local Source key, which
 * only the write path (PHP's SubjectIdParser) has.
 */
export class SubjectId {

	public readonly text: string;

	/**
	 * The key of the Source that produced this Subject, or null for the local one.
	 */
	public readonly source: string | null;

	public readonly localId: string;

	public constructor( text: string ) {
		const parts = splitSubjectId( text );

		if ( parts === null ) {
			throw new Error( 'Subject ID has the wrong format. ID: ' + text );
		}

		this.text = text;
		this.source = parts[ 0 ];
		this.localId = parts[ 1 ];
	}

	public isLocal(): boolean {
		return this.source === null;
	}

	/**
	 * Whether text is a well-formed Subject id in either form. Syntax only: whether the Source exists,
	 * and whether it recognizes the localId, is answered at resolution time.
	 */
	public static isValid( text: string ): boolean {
		return splitSubjectId( text ) !== null;
	}

	public static isValidLocalId( text: string ): boolean {
		return LOCAL_ID_PATTERN.test( text );
	}

	public static isValidSourceKey( text: string ): boolean {
		return SOURCE_KEY_PATTERN.test( text );
	}

}

function splitSubjectId( text: string ): [ string | null, string ] | null {
	const colonPosition = text.indexOf( ':' );

	if ( colonPosition === -1 ) {
		return LOCAL_ID_PATTERN.test( text ) ? [ null, text ] : null;
	}

	const source = text.slice( 0, colonPosition );
	const localId = text.slice( colonPosition + 1 );

	if ( !SOURCE_KEY_PATTERN.test( source ) || !FOREIGN_LOCAL_ID_PATTERN.test( localId ) ) {
		return null;
	}

	return [ source, localId ];
}
