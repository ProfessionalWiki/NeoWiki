const BARE_PATTERN = /^s[123456789ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnopqrstuvwxyz]{14}$/;
const QUALIFIED_PATTERN = /^([A-Za-z0-9+_-]+):([A-Za-z0-9._~!$&'()*+,;=:@-]+)$/;

export class SubjectId {

	public readonly text: string;
	private readonly source: string | null;
	private readonly localId: string;

	public constructor( text: string ) {
		if ( BARE_PATTERN.test( text ) ) {
			this.source = null;
			this.localId = text;
		} else {
			const match = QUALIFIED_PATTERN.exec( text );

			if ( match === null ) {
				throw new Error( 'Subject ID has the wrong format. ID: ' + text );
			}

			this.source = match[ 1 ];
			this.localId = match[ 2 ];
		}

		this.text = text;
	}

	public static isValid( text: string ): boolean {
		return BARE_PATTERN.test( text ) || QUALIFIED_PATTERN.test( text );
	}

	/**
	 * The source key, or null for a local Subject (bare id form).
	 */
	public getSource(): string | null {
		return this.source;
	}

	public getLocalId(): string {
		return this.localId;
	}

}
